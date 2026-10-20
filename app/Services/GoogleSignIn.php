<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Logger;

/**
 * "Sign in with Google" for schools on Google Workspace (OpenID Connect, authorization code flow with PKCE),
 * written with curl and OpenSSL so no library is needed. It only finds existing Sibol accounts by their school
 * email: it never creates accounts or changes roles. The ID token is checked here, not trusted: Google's
 * signature (RS256, keys from Google's published list, cached), issuer, audience, expiry, the nonce sent from
 * this session, a verified email and the school's Workspace domain (hd).
 */
final class GoogleSignIn
{
    public const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];
    private const SESSION_KEY = 'google_sign_in';
    private const WAIT_SECONDS = 600;
    private const LEEWAY = 60;

    /** @var callable(string $method, string $url, array<string,string> $form): array{status:int, body:string, max_age:?int} */
    private $http;
    private string $keyCache;

    /** @param callable|null $http for tests; the default uses curl */
    public function __construct(?callable $http = null, ?string $keyCache = null)
    {
        $this->http = $http ?? [self::class, 'curl'];
        $this->keyCache = $keyCache ?? BASE_PATH . '/storage/cache/google-keys.json';
    }

    /** Client ID, client secret and at least one Workspace domain are set in .env. */
    public static function configured(): bool
    {
        return trim((string) Config::get('google.client_id', '')) !== ''
            && trim((string) Config::get('google.client_secret', '')) !== ''
            && self::domains() !== [];
    }

    /** @return list<string> the school's Google Workspace domains, lower case */
    public static function domains(): array
    {
        $list = array_map(static fn ($d) => strtolower(trim((string) $d)), explode(',', (string) Config::get('google.domains', '')));
        return array_values(array_unique(array_filter($list, static fn ($d) => $d !== '')));
    }

    /** What the Integrations page shows: never the client secret. */
    public static function status(): array
    {
        return [
            'configured' => self::configured(),
            'client_id' => trim((string) Config::get('google.client_id', '')),
            'has_secret' => trim((string) Config::get('google.client_secret', '')) !== '',
            'domains' => self::domains(),
            'redirect_uri' => self::redirectUri(),
        ];
    }

    /** The address to register in Google Cloud as the authorized redirect URI. */
    public static function redirectUri(): string
    {
        return rtrim((string) Config::get('app.url', ''), '/') . '/auth/google/callback';
    }

    /** Remembers a new state, nonce and PKCE verifier in the session and returns the Google address to go to. */
    public function start(string $intended, int $now): string
    {
        $verifier = self::base64url(random_bytes(48));
        $pending = [
            'state' => self::base64url(random_bytes(24)),
            'nonce' => self::base64url(random_bytes(24)),
            'verifier' => $verifier,
            'until' => $now + self::WAIT_SECONDS,
            'to' => $intended,
        ];
        $_SESSION[self::SESSION_KEY] = $pending;
        $domains = self::domains();
        $params = [
            'client_id' => (string) Config::get('google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $pending['state'],
            'nonce' => $pending['nonce'],
            'code_challenge' => self::base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ];
        if (count($domains) === 1) {
            // A hint for Google's account chooser; the domain is still checked in the token.
            $params['hd'] = $domains[0];
        }
        return self::AUTHORIZE_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Finishes the round trip: the state must be this session's, the code is exchanged for an ID token, and the
     * token is verified. Returns the verified email (lower case) and the place to go afterwards.
     *
     * @param array<string,mixed> $query the callback's query string
     * @return array{email: string, to: string}
     * @throws GoogleSignInError with a message for the person
     */
    public function finish(array $query, int $now): array
    {
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($pending) || (int) ($pending['until'] ?? 0) < $now
            || !is_string($query['state'] ?? null) || !hash_equals((string) $pending['state'], (string) $query['state'])) {
            throw new GoogleSignInError(t('That Google sign-in did not start here or took too long. Try again.'), 'state');
        }
        if (isset($query['error'])) {
            throw new GoogleSignInError(t('Google sign-in was cancelled.'), 'cancelled');
        }
        $code = (string) ($query['code'] ?? '');
        if ($code === '') {
            throw new GoogleSignInError(t('Google did not send a sign-in code. Try again.'), 'no code');
        }
        $response = ($this->http)('POST', self::TOKEN_URL, [
            'code' => $code,
            'client_id' => (string) Config::get('google.client_id'),
            'client_secret' => (string) Config::get('google.client_secret'),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => (string) $pending['verifier'],
        ]);
        $token = json_decode((string) $response['body'], true);
        if ($response['status'] !== 200 || !is_array($token) || !is_string($token['id_token'] ?? null)) {
            Logger::warning('Google token exchange failed', ['status' => $response['status'], 'error' => is_array($token) ? ($token['error'] ?? null) : null]);
            throw new GoogleSignInError(t('Google sign-in could not be completed. Try again, or sign in with your password.'), 'token exchange');
        }
        $claims = $this->verify($token['id_token'], (string) $pending['nonce'], $now);
        return ['email' => strtolower((string) $claims['email']), 'to' => (string) ($pending['to'] ?? '')];
    }

    /**
     * Checks an ID token and returns its claims.
     *
     * @return array<string,mixed>
     * @throws GoogleSignInError
     */
    public function verify(string $jwt, string $nonce, int $now): array
    {
        $parts = explode('.', $jwt);
        $header = count($parts) === 3 ? json_decode(self::unbase64url($parts[0]), true) : null;
        $claims = count($parts) === 3 ? json_decode(self::unbase64url($parts[1]), true) : null;
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null)) {
            throw self::rejected('malformed token');
        }
        $key = $this->key($header['kid'], $now);
        if ($key === null || openssl_verify($parts[0] . '.' . $parts[1], self::unbase64url($parts[2]), $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw self::rejected('signature');
        }
        $audience = (array) ($claims['aud'] ?? []);
        if (!in_array($claims['iss'] ?? '', self::ISSUERS, true)) {
            throw self::rejected('issuer');
        }
        if (!in_array((string) Config::get('google.client_id'), $audience, true)) {
            throw self::rejected('audience');
        }
        if ((int) ($claims['exp'] ?? 0) < $now - self::LEEWAY || (int) ($claims['iat'] ?? PHP_INT_MAX) > $now + self::LEEWAY) {
            throw self::rejected('expired');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw self::rejected('nonce');
        }
        if (($claims['email_verified'] ?? false) !== true || !is_string($claims['email'] ?? null)) {
            throw self::rejected('email not verified');
        }
        $hd = strtolower((string) ($claims['hd'] ?? ''));
        if ($hd === '' || !in_array($hd, self::domains(), true)) {
            throw new GoogleSignInError(t('Use your school Google account (:domains).', ['domains' => implode(', ', self::domains())]), 'domain');
        }
        return $claims;
    }

    /**
     * The Sibol account a verified school email belongs to. Nobody is created or given a role here. An invited
     * account is set up by its first Google sign-in (its invitation link stops working).
     *
     * @return array<string,mixed>
     * @throws GoogleSignInError
     */
    public static function account(string $email): array
    {
        $user = Db::one('SELECT * FROM users WHERE email = :e AND anonymized_at IS NULL', ['e' => $email]);
        if (!$user) {
            throw new GoogleSignInError(t('No Sibol account uses :email. Ask your school administrator, or sign in with your username and password.', ['email' => $email]), 'no account');
        }
        if ($user['status'] === 'deactivated') {
            throw new GoogleSignInError(t('This account has been deactivated. Contact your school administrator if you need access.'), 'deactivated');
        }
        if ($user['status'] === 'invited') {
            Db::transaction(static function () use ($user): void {
                Db::update('users', ['status' => 'active', 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
                Db::exec(
                    "UPDATE user_tokens SET revoked_at = :n WHERE user_id = :u AND purpose = 'invitation' AND used_at IS NULL AND revoked_at IS NULL",
                    ['n' => Db::now(), 'u' => (int) $user['id']]
                );
            });
            Audit::log('auth.invitation_accepted', 'user', (int) $user['id'], ['via' => 'google'], $user);
            $user = (array) Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $user['id']]);
        }
        return $user;
    }

    /**
     * Signs the person in, or, when they use two-step sign-in, holds the sign-in until their code matches (Google
     * vouches for the password step only). Returns where to go next.
     */
    public static function complete(array $user, string $to): string
    {
        $target = $to !== '' ? $to : home_path((string) $user['role']);
        if (TwoStep::enabled($user)) {
            TwoStep::begin($user, $target);
            return '/login/code';
        }
        Auth::login($user);
        Audit::log('auth.login', 'user', (int) $user['id'], ['via' => 'google'], $user);
        return $target;
    }

    /** Google's public key for a key id, from the cached list (fetched again when the id is new or the list is old). */
    private function key(string $kid, int $now): ?string
    {
        $cache = is_file($this->keyCache) ? json_decode((string) file_get_contents($this->keyCache), true) : null;
        $fresh = is_array($cache) && (int) ($cache['until'] ?? 0) > $now;
        if (!$fresh || !isset($cache['keys'][$kid])) {
            $response = ($this->http)('GET', self::KEYS_URL, []);
            $list = json_decode((string) $response['body'], true);
            if ($response['status'] !== 200 || !is_array($list['keys'] ?? null)) {
                return null;
            }
            $keys = [];
            foreach ($list['keys'] as $jwk) {
                if (($jwk['kty'] ?? '') === 'RSA' && is_string($jwk['kid'] ?? null) && is_string($jwk['n'] ?? null) && is_string($jwk['e'] ?? null)) {
                    $keys[$jwk['kid']] = self::pemFromJwk($jwk['n'], $jwk['e']);
                }
            }
            $cache = ['until' => $now + max(300, min(86400, (int) ($response['max_age'] ?? 3600))), 'keys' => $keys];
            @file_put_contents($this->keyCache, json_encode($cache));
        }
        return $cache['keys'][$kid] ?? null;
    }

    /** An RSA public key (modulus and exponent, base64url) as PEM, the form openssl_verify() reads. */
    public static function pemFromJwk(string $n, string $e): string
    {
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            return self::der(0x02, ($bytes === '' || ord($bytes[0]) > 0x7f) ? "\x00" . $bytes : $bytes);
        };
        $rsaKey = self::der(0x30, $integer(self::unbase64url($n)) . $integer(self::unbase64url($e)));
        $algorithm = (string) hex2bin('300d06092a864886f70d0101010500'); // rsaEncryption, NULL parameters
        $info = self::der(0x30, $algorithm . self::der(0x03, "\x00" . $rsaKey));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($info), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function unbase64url(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $value;
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $value;
    }

    private static function rejected(string $why): GoogleSignInError
    {
        Logger::warning('Google ID token refused', ['reason' => $why]);
        return new GoogleSignInError(t('Google sign-in could not be checked. Try again, or sign in with your password.'), $why);
    }

    /**
     * @param array<string,string> $form
     * @return array{status: int, body: string, max_age: ?int}
     */
    private static function curl(string $method, string $url, array $form): array
    {
        $maxAge = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$maxAge): int {
                if (stripos($line, 'cache-control:') === 0 && preg_match('/max-age=(\d+)/i', $line, $m)) {
                    $maxAge = (int) $m[1];
                }
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form, '', '&'));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            Logger::warning('Google request failed', ['url' => $url, 'error' => curl_error($ch)]);
            $body = '';
        }
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'max_age' => $maxAge];
    }
}

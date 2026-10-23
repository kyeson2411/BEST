<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\GoogleSignIn;
use Sibol\Services\GoogleSignInError;
use Sibol\Services\Tokens;
use Sibol\Services\Totp;
use Sibol\Services\TwoStep;

/**
 * Item 16: "Sign in with Google" for the school's Workspace accounts. Google is replaced by a fake that answers
 * the token and key requests; ID tokens are signed here with a throwaway key.
 */
final class GoogleSignInTest extends IntegrationTestCase
{
    /** A throwaway RSA key made for this test only: it signs fake Google ID tokens, and nothing trusts it. */
    private const TEST_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC22u5dtug6TBYK
Km4SYugW7xNJr5X4lxH3BldsC5svc+EJKjQWUjTdhFbjYdsOaqLGqbU2u8woJ6Vc
8nzxl6RIzTwSq3JTqK+e3KQEIIjBDZOQalLHJeb3Kn9y4tgce9DXvIUzfkM/WB+f
GkE6OPSADvTIjjnIPWXYFY8cBl80EmolKBdy835d27ZltEfB11XbHauztln6JxOJ
tLCBFvPqUYxOnCe+Kse0IjHQXn+dB8mE3j4QF1NH+V3bRVBBf07wcGkz62L4aIpz
cIxW7vbRSwhISvxTIMmYq9wLBNHQa2Ny5eA2Wtp76WDpN8xO8LY2ntk5MltK6Sf1
b3sBde/hAgMBAAECggEACJrM1MQisXubHedZPqxA17NwaOtGV2hkhAdcXc0+BEGj
uVavFM3RhwsPO6wDLu59snOR/31e4hTbh0Ax+Px10qc1yHsg6ibp/MPNRBRz2wHU
i9s1AgIamE77qGBcYxrAfSZ17jJm37g55LbLkv3TI6OQiU+IEJAzFrQNA/iYyk5v
I8g2sHLE4zU3VZr/WT/wlcJrCe8l09O/Z9y89HhGLYaqlSg9/QXXZDiJk7Wmsk6X
qQ3VjdSZq8JyS4zygY2WxIX2MfXWNhPFx/PBRv04LRDBg+O4GzGr77NYRWrXhqGh
FLeUkuii9m9D6XlhzA6871YENmD8/G2sdTs5CNAs8QKBgQDkYAcjZOXnTvxH5LvJ
bXJPAokJV6aNvdy868uGDaL3oLvaMezunSNHocVRPzoeHqrbKNTdgi9rm9U84mmW
UECsgCdzX39Yk4bMiI/uYJcsC9wT7wQbP05DgI+6MNplbhnPxawSkTS2f7Q+7Xd7
7CdzaERVJbpTQlaVRvoFXAM0OwKBgQDM+U/luO5EjIo4JcyV4juzsiWq9pvv2TZ6
11dVg/Puhkb+fruUVYFSvl0PXROUTHNYo/bmD42h6jjgZCs+jKE5Af0HxCZMefer
A6cSnHxip3kIjKPZh+Myo5/z98pi9vmccmaFYvkpCvbY38C035ZghGWCtxAoH5gk
3kxuVqe2kwKBgQDMMszPKf2jeG19PxllvtXRrbuoPdYvsM85oUBmybSo4/Bz5eYr
aRfNiekrf3RQ0C0HLxg+Q5RxDHJ7MRsmCvX5B9G+iGoOYkLKiWTgyT8CZExRCotK
ba1mEALzgZEV1n9/1wjHxUwzzjX1elE23e5cc6N92vEiVxOiAEe4GRDIVwKBgGl4
5IcF6P03ACyyLpr1pdjqk31sloMN133Qby0e0Bdt2vw7xFQox3oHYKV2NIv75uK2
xGZPnIazmn7ErIfsH03RWx1y7m4t2KaUI5VtFohBOwOOMwXNabh1/+mvGOJhycGa
EG0Up7dZ3/lq8aNg5eVWW1Gu105+K3DVPqkMSBKnAoGBALRfzchgvjao6qshTsKd
xg/6NBxpFxLw/qTpHlbo7KuO1xMJ0vaHYmvoAAobwhMtGsz9k0B+VXru5yMjTckO
VNUA5FUtduYtCwsLdPrSE8/yT2LYhL2LmclrpjZTTArlT5Y2kt+KH73ZsUr0myU2
ExE+zc5sM4s7cFTZGTUbvMD2
-----END PRIVATE KEY-----
PEM;
    private const NOW = 1792000000;
    private const CLIENT = 'test-client.apps.googleusercontent.com';

    /** @var list<array{0:string,1:string,2:array}> requests the fake Google received */
    private array $calls = [];
    private string $cache = '';
    /** @var array<string,mixed> claims of the next ID token the fake hands out */
    private array $next = [];
    private string $nonce = '';
    private string $verifier = '';

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Config::set('google.client_id', self::CLIENT);
        Config::set('google.client_secret', 'test-secret');
        Config::set('google.domains', 'demo.sibol.test, Example.org');
        $this->cache = sys_get_temp_dir() . '/sibol-google-keys-' . bin2hex(random_bytes(4)) . '.json';
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        foreach (['google.client_id', 'google.client_secret', 'google.domains'] as $key) {
            Config::set($key, '');
        }
        @unlink($this->cache);
        $_SESSION = [];
        parent::tearDown();
    }

    public function testItIsOnlyOfferedWhenTheClientAndADomainAreSet(): void
    {
        self::assertTrue(GoogleSignIn::configured());
        self::assertSame(['demo.sibol.test', 'example.org'], GoogleSignIn::domains());
        self::assertStringEndsWith('/auth/google/callback', GoogleSignIn::redirectUri());
        Config::set('google.domains', ' , ');
        self::assertFalse(GoogleSignIn::configured(), 'a Workspace domain is required');
    }

    public function testStartingSendsStateNonceAndAPkceChallenge(): void
    {
        $url = $this->google()->start('/teacher/gradebook', self::NOW);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        self::assertStringStartsWith(GoogleSignIn::AUTHORIZE_URL . '?', $url);
        $pending = $_SESSION['google_sign_in'];
        self::assertSame([self::CLIENT, GoogleSignIn::redirectUri(), 'code', 'openid email profile', 'S256'], [$q['client_id'], $q['redirect_uri'], $q['response_type'], $q['scope'], $q['code_challenge_method']]);
        self::assertSame([$pending['state'], $pending['nonce']], [$q['state'], $q['nonce']]);
        self::assertSame(GoogleSignIn::base64url(hash('sha256', $pending['verifier'], true)), $q['code_challenge']);
        self::assertArrayNotHasKey('hd', $q, 'two domains: no hint');
        self::assertArrayNotHasKey('client_secret', $q);
        self::assertGreaterThanOrEqual(43, strlen($pending['verifier']));
    }

    public function testAGoodTokenGivesTheVerifiedSchoolEmail(): void
    {
        $google = $this->google();
        $state = $this->begin($google);
        $result = $google->finish(['state' => $state, 'code' => 'abc'], self::NOW + 20);
        self::assertSame(['email' => 'joy.santiago@demo.sibol.test', 'to' => '/teacher'], $result);
        [$method, $url, $form] = $this->calls[0];
        self::assertSame(['POST', GoogleSignIn::TOKEN_URL, 'abc', 'authorization_code', 'test-secret'], [$method, $url, $form['code'], $form['grant_type'], $form['client_secret']]);
        self::assertSame($this->verifier, $form['code_verifier'], 'the PKCE verifier goes with the code');
        self::assertArrayNotHasKey('google_sign_in', $_SESSION, 'a state works once');
        self::assertSame('GET', $this->calls[1][0]);

        // The keys are cached: a second sign-in fetches only the token.
        $this->calls = [];
        $state = $this->begin($google);
        $google->finish(['state' => $state, 'code' => 'def'], self::NOW + 40);
        self::assertCount(1, $this->calls);
    }

    /** @dataProvider badTokens */
    public function testTokensThatAreNotRightAreRefused(callable $change, string $reason): void
    {
        $google = $this->google();
        $state = $this->begin($google);
        $change($this);
        try {
            $google->finish(['state' => $state, 'code' => 'abc'], self::NOW + 20);
            self::fail('accepted a token with the wrong ' . $reason);
        } catch (GoogleSignInError $e) {
            self::assertSame($reason, $e->reason);
            self::assertSame($reason === 'domain'
                ? 'Use your school Google account (demo.sibol.test, example.org).'
                : 'Google sign-in could not be checked. Try again, or sign in with your password.', $e->getMessage());
        }
    }

    /** @return array<string, array{0: callable, 1: string}> */
    public function badTokens(): array
    {
        return [
            'another app' => [static fn (self $t) => $t->next['aud'] = 'someone-else.apps.googleusercontent.com', 'audience'],
            'another issuer' => [static fn (self $t) => $t->next['iss'] = 'https://evil.example', 'issuer'],
            'expired' => [static fn (self $t) => $t->next['exp'] = self::NOW - 3600, 'expired'],
            'issued in the future' => [static fn (self $t) => $t->next['iat'] = self::NOW + 3600, 'expired'],
            'another nonce' => [static fn (self $t) => $t->next['nonce'] = 'replayed', 'nonce'],
            'email not verified' => [static fn (self $t) => $t->next['email_verified'] = false, 'email not verified'],
            'personal account' => [static fn (self $t) => $t->next['hd'] = null, 'domain'],
            'another school' => [static fn (self $t) => $t->next['hd'] = 'other-school.edu.ph', 'domain'],
            'unsigned' => [static fn (self $t) => $t->next['_alg'] = 'none', 'malformed token'],
            'tampered' => [static fn (self $t) => $t->next['_tamper'] = true, 'signature'],
            'unknown key' => [static fn (self $t) => $t->next['_kid'] = 'not-in-the-list', 'signature'],
        ];
    }

    public function testTheRoundTripMustBeThisSessionsAndRecent(): void
    {
        $google = $this->google();
        $this->begin($google);
        $this->assertRefused($google, ['state' => 'forged', 'code' => 'abc'], self::NOW, 'state');
        $this->begin($google);
        $this->assertRefused($google, ['code' => 'abc'], self::NOW, 'state');
        // Nothing was started in this session at all.
        $this->assertRefused($google, ['state' => 'anything', 'code' => 'abc'], self::NOW, 'state');
        $state = $this->begin($google);
        $this->assertRefused($google, ['state' => $state, 'code' => 'abc'], self::NOW + 601, 'state');
        $state = $this->begin($google);
        $this->assertRefused($google, ['state' => $state, 'error' => 'access_denied'], self::NOW, 'cancelled');
        $state = $this->begin($google);
        $this->assertRefused($google, ['state' => $state], self::NOW, 'no code');
        self::assertSame([], $this->calls, 'nothing was sent to Google');

        // A state works once, so Google's answer cannot be used again.
        $state = $this->begin($google);
        $google->finish(['state' => $state, 'code' => 'abc'], self::NOW + 20);
        $this->assertRefused($google, ['state' => $state, 'code' => 'abc'], self::NOW + 30, 'state');
    }

    public function testAFailedTokenExchangeIsRefusedWithoutDetails(): void
    {
        $google = new GoogleSignIn(fn (): array => ['status' => 400, 'max_age' => null, 'body' => '{"error":"invalid_grant"}'], $this->cache);
        $google->start('/teacher', self::NOW);
        $this->assertRefused($google, ['state' => $_SESSION['google_sign_in']['state'], 'code' => 'used-before'], self::NOW, 'token exchange');
    }

    public function testOnlyExistingAccountsAreFoundAndNothingAboutThemChanges(): void
    {
        $joy = $this->person('joy.santiago');
        $found = GoogleSignIn::account('JOY.SANTIAGO@demo.sibol.test');
        self::assertSame([(int) $joy['id'], 'teacher', $joy['updated_at']], [(int) $found['id'], $found['role'], $found['updated_at']]);
        $this->assertNoAccount('nobody@demo.sibol.test', 'no account', 'No B.E.S.T. Learning and Interactive Study System account uses nobody@demo.sibol.test');
        Db::update('users', ['status' => 'deactivated'], ['id' => (int) $joy['id']]);
        try {
            $this->assertNoAccount('joy.santiago@demo.sibol.test', 'deactivated', 'has been deactivated');
        } finally {
            Db::update('users', ['status' => 'active'], ['id' => (int) $joy['id']]);
        }
    }

    public function testAnInvitedAccountIsSetUpByItsFirstGoogleSignIn(): void
    {
        $joy = $this->person('joy.santiago');
        $id = Db::insert('users', [
            'organization_id' => (int) $joy['organization_id'], 'role' => 'teacher', 'status' => 'invited', 'email' => 'new.teacher@demo.sibol.test',
            'username' => 'new.teacher', 'given_name' => 'New', 'family_name' => 'Teacher', 'is_demo' => 1,
        ]);
        try {
            $token = Tokens::issue($id, 'invitation', null);
            $user = GoogleSignIn::account('new.teacher@demo.sibol.test');
            self::assertSame(['active', 'teacher', null], [$user['status'], $user['role'], $user['password_hash']]);
            self::assertNull(Tokens::find($token, 'invitation') === null ? null : 'still works', 'the invitation link stops working');
            self::assertSame('{"via":"google"}', Db::value("SELECT metadata FROM audit_logs WHERE action = 'auth.invitation_accepted' AND target_id = :u", ['u' => $id]));
        } finally {
            Db::exec('DELETE FROM user_tokens WHERE user_id = :u', ['u' => $id]);
            Db::exec('DELETE FROM users WHERE id = :u', ['u' => $id]);
        }
    }

    public function testTwoStepSignInStillAsksForItsCode(): void
    {
        $joy = $this->person('joy.santiago');
        self::assertSame('/teacher/gradebook', GoogleSignIn::complete($joy, '/teacher/gradebook'));
        self::assertSame((int) $joy['id'], $_SESSION['uid']);
        self::assertSame('{"via":"google"}', Db::value("SELECT metadata FROM audit_logs WHERE action = 'auth.login' AND actor_id = :u ORDER BY id DESC LIMIT 1", ['u' => (int) $joy['id']]));

        $_SESSION = [];
        $secret = TwoStep::setupSecret(self::NOW);
        TwoStep::enable($joy, $secret, Totp::code($secret, Totp::step(self::NOW)), self::NOW);
        try {
            self::assertSame('/login/code', GoogleSignIn::complete($this->person('joy.santiago'), ''));
            self::assertArrayNotHasKey('uid', $_SESSION, 'not signed in until the code matches');
            self::assertSame('/teacher', $_SESSION['two_step']['to']);
        } finally {
            TwoStep::disable($this->person('joy.santiago'), $joy);
        }
    }

    public function testPemFromJwkVerifiesWhatTheKeyItCameFromSigned(): void
    {
        $key = openssl_pkey_get_private(self::TEST_KEY);
        $details = openssl_pkey_get_details($key);
        $pem = GoogleSignIn::pemFromJwk(GoogleSignIn::base64url($details['rsa']['n']), GoogleSignIn::base64url($details['rsa']['e']));
        self::assertSame(trim($details['key']), trim($pem), 'the same public key as OpenSSL writes it');
        openssl_sign('hello', $signature, $key, OPENSSL_ALGO_SHA256);
        self::assertSame(1, openssl_verify('hello', $signature, $pem, OPENSSL_ALGO_SHA256));
        self::assertSame(0, openssl_verify('hellO', $signature, $pem, OPENSSL_ALGO_SHA256));
    }

    // ------------------------------------------------------------------ the fake Google

    private function google(): GoogleSignIn
    {
        $this->next = [];
        return new GoogleSignIn(function (string $method, string $url, array $form): array {
            $this->calls[] = [$method, $url, $form];
            if ($url === GoogleSignIn::KEYS_URL) {
                $details = openssl_pkey_get_details(openssl_pkey_get_private(self::TEST_KEY));
                return ['status' => 200, 'max_age' => 21600, 'body' => (string) json_encode(['keys' => [
                    ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key-1', 'n' => GoogleSignIn::base64url($details['rsa']['n']), 'e' => GoogleSignIn::base64url($details['rsa']['e'])],
                ]])];
            }
            return ['status' => 200, 'max_age' => null, 'body' => (string) json_encode(['access_token' => 'x', 'id_token' => $this->idToken()])];
        }, $this->cache);
    }

    /** Starts a round trip and returns its state, with the nonce the next token will carry. */
    private function begin(GoogleSignIn $google): string
    {
        $google->start('/teacher', self::NOW);
        $this->next = [];
        $this->nonce = (string) $_SESSION['google_sign_in']['nonce'];
        $this->verifier = (string) $_SESSION['google_sign_in']['verifier'];
        return (string) $_SESSION['google_sign_in']['state'];
    }

    private function idToken(): string
    {
        $claims = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT,
            'sub' => '1234567890',
            'email' => 'Joy.Santiago@demo.sibol.test',
            'email_verified' => true,
            'hd' => 'demo.sibol.test',
            'nonce' => $this->nonce,
            'iat' => self::NOW,
            'exp' => self::NOW + 3600,
        ], $this->next);
        $alg = $claims['_alg'] ?? 'RS256';
        $kid = $claims['_kid'] ?? 'test-key-1';
        $tamper = !empty($claims['_tamper']);
        unset($claims['_alg'], $claims['_kid'], $claims['_tamper']);
        $claims = array_filter($claims, static fn ($v) => $v !== null);
        $input = GoogleSignIn::base64url((string) json_encode(['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT'])) . '.' . GoogleSignIn::base64url((string) json_encode($claims));
        openssl_sign($input, $signature, openssl_pkey_get_private(self::TEST_KEY), OPENSSL_ALGO_SHA256);
        if ($tamper) {
            $claims['email'] = 'teresa.villanueva@demo.sibol.test';
            $input = explode('.', $input)[0] . '.' . GoogleSignIn::base64url((string) json_encode($claims));
        }
        return $input . '.' . ($alg === 'none' ? '' : GoogleSignIn::base64url($signature));
    }

    private function assertNoAccount(string $email, string $reason, string $message): void
    {
        try {
            GoogleSignIn::account($email);
            self::fail('found an account for ' . $email);
        } catch (GoogleSignInError $e) {
            self::assertSame($reason, $e->reason);
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    private function assertRefused(GoogleSignIn $google, array $query, int $now, string $reason): void
    {
        try {
            $google->finish($query, $now);
            self::fail('accepted, expected "' . $reason . '"');
        } catch (GoogleSignInError $e) {
            self::assertSame($reason, $e->reason);
        }
    }
}

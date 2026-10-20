<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Audit;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\RateLimiter;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Services\Qr;
use Sibol\Services\Totp;
use Sibol\Services\TwoStep;

/**
 * Two-step sign-in: the code step after the password (/login/code), and turning it on, renewing the recovery
 * codes and turning it off in Account and preferences (/settings/two-step, administrators and teachers).
 */
final class TwoStepController extends Controller
{
    /** The second step of signing in: a code from the app or a recovery code. */
    public function showChallenge(): Response
    {
        if (TwoStep::pending() === null) {
            $this->flash('info', t('Sign in again. The code step waits five minutes.'));
            return $this->redirect('/login');
        }
        return $this->view('auth/two_step', ['title' => t('Enter your code')], 'layouts/auth');
    }

    public function challenge(): Response
    {
        $pending = TwoStep::pending();
        if ($pending === null) {
            $this->flash('info', t('Sign in again. The code step waits five minutes.'));
            return $this->redirect('/login');
        }
        $user = $pending['user'];
        if (!RateLimiter::attempt('two-step', (string) $user['id'], 6, 900)) {
            TwoStep::forget();
            $this->flash('error', t('Too many codes that did not match. Wait 15 minutes, then sign in again.'));
            return $this->redirect('/login');
        }
        // Like a wrong password: the message sits under the field, and the code typed is not kept.
        if (!TwoStep::available()) {
            Session::withInput([], ['code' => t('Codes cannot be checked: the server is missing its APP_KEY. Ask your school administrator.')]);
            return $this->redirect('/login/code');
        }
        $how = TwoStep::verify($user, $this->request->str('code'), time());
        if ($how === null) {
            Audit::log('auth.two_step_failed', 'user', (int) $user['id'], [], $user);
            Session::withInput([], ['code' => t('That code does not match. Type the 6 digits your app shows now, or one of your recovery codes.')]);
            return $this->redirect('/login/code');
        }
        RateLimiter::clear('two-step', (string) $user['id']);
        TwoStep::forget();
        Auth::login($user);
        Audit::log('auth.login', 'user', (int) $user['id'], ['second_step' => $how], $user);
        if ($how === 'recovery') {
            $left = TwoStep::recoveryCodesLeft((int) $user['id']);
            $this->flash($left <= 3 ? 'warning' : 'info', tp(
                'You used a recovery code. :count is left; you can make new ones in Account and preferences.',
                'You used a recovery code. :count are left; you can make new ones in Account and preferences.',
                $left
            ));
        }
        return $this->redirect($pending['to']);
    }

    /** Turning it on: the QR code and key for the app, then the first code from it. */
    public function setup(): Response
    {
        $user = $this->user();
        $stop = $this->notNow($user, false);
        if ($stop !== null) {
            return $stop;
        }
        $secret = TwoStep::setupSecret(time());
        $issuer = trim((string) Config::get('app.name', 'Sibol') . ' ' . (string) Config::get('school.short_name', ''));
        $account = (string) (($user['username'] ?? '') ?: $user['email']);
        $uri = Totp::uri($secret, $account, $issuer);
        return $this->view('shared/two_step_setup', [
            'title' => t('Set up two-step sign-in'),
            'crumbs' => [[t('Account and preferences'), '/settings'], [t('Two-step sign-in'), null]],
            'qr' => Qr::encode($uri)->svg('two-step__qr', t('QR code for your authenticator app')),
            'uri' => $uri,
            'key' => trim(chunk_split(Totp::base32($secret), 4, ' ')),
            'account' => $account,
            'issuer' => $issuer,
        ]);
    }

    public function enable(): Response
    {
        $user = $this->user();
        $stop = $this->notNow($user, false);
        if ($stop !== null) {
            return $stop;
        }
        $this->checkPassword($user, '/settings/two-step');
        $codes = TwoStep::enable($user, TwoStep::setupSecret(time()), $this->request->str('code'), time());
        if ($codes === null) {
            $this->fail('code', t('That code does not match. Check that the phone sets its time automatically, then type the code the app shows now.'), '/settings/two-step');
        }
        $_SESSION['recovery_codes'] = $codes;
        $this->flash('success', t('Two-step sign-in is on.'));
        return $this->redirect('/settings/two-step/codes');
    }

    /** New recovery codes, shown once on the next page; the old ones stop working. */
    public function renewCodes(): Response
    {
        $user = $this->user();
        $stop = $this->notNow($user, true);
        if ($stop !== null) {
            return $stop;
        }
        $this->checkPassword($user, '/settings#two-step');
        $_SESSION['recovery_codes'] = TwoStep::replaceRecoveryCodes((int) $user['id']);
        Audit::log('auth.recovery_codes_renewed', 'user', (int) $user['id']);
        $this->flash('success', t('New recovery codes made. The old ones no longer work.'));
        return $this->redirect('/settings/two-step/codes');
    }

    /** The recovery codes just made: shown once, then gone from the session. */
    public function codes(): Response
    {
        $codes = $_SESSION['recovery_codes'] ?? null;
        unset($_SESSION['recovery_codes']);
        if (!is_array($codes) || $codes === []) {
            return $this->redirect('/settings#two-step');
        }
        return $this->view('shared/two_step_codes', [
            'title' => t('Your recovery codes'),
            'crumbs' => [[t('Account and preferences'), '/settings'], [t('Recovery codes'), null]],
            'codes' => $codes,
        ]);
    }

    public function disable(): Response
    {
        $user = $this->user();
        $stop = $this->notNow($user, true);
        if ($stop !== null) {
            return $stop;
        }
        $this->checkPassword($user, '/settings#two-step');
        TwoStep::disable($user, $user);
        $this->flash('success', t('Two-step sign-in is off. Your password alone signs you in.'));
        return $this->redirect('/settings#two-step');
    }

    /** Back to the settings when it cannot be done now: no APP_KEY, or already on (or off). */
    private function notNow(array $user, bool $needsOn): ?Response
    {
        if (!TwoStep::available()) {
            $this->flash('error', t('Two-step sign-in is not ready on this server yet. A school administrator needs to set APP_KEY (see README).'));
            return $this->redirect('/settings#two-step');
        }
        return TwoStep::enabled($user) === $needsOn ? null : $this->redirect('/settings#two-step');
    }

    /** Changing two-step sign-in asks for the password, so an unattended computer is not enough. */
    private function checkPassword(array $user, string $back): void
    {
        $password = (string) ($this->request->post['two_step_password'] ?? '');
        if (!RateLimiter::attempt('two-step-password', (string) $user['id'], 8, 900)) {
            $this->fail('two_step_password', t('Too many tries. Wait 15 minutes, then try again.'), $back);
        }
        if ($password === '' || !password_verify($password, (string) $user['password_hash'])) {
            $this->fail('two_step_password', t('That is not your current password.'), $back);
        }
        RateLimiter::clear('two-step-password', (string) $user['id']);
    }
}

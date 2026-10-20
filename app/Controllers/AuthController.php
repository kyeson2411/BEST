<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Audit;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\RateLimiter;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Services\Tokens;

final class AuthController extends Controller
{
    /** Used so a missing account takes as long to check as a real one. */
    private const DUMMY_HASH = '$2y$10$9bXQh0k1G8r0O1y2u4m7xu3YbWQ5bQe8bq0m1nY6u8VbI6x2gq3eK';

    public function showLogin(): Response
    {
        return $this->view('auth/login', [
            'title' => t('Sign in'),
            'school' => $this->singleSchoolName(),
            'demoAccounts' => $this->demoAccounts(),
        ], 'layouts/auth');
    }

    public function login(): Response
    {
        $email = mb_strtolower($this->request->str('email'));
        $password = (string) ($this->request->post['password'] ?? '');
        $ip = $this->request->ip();

        if (!RateLimiter::attempt('login-ip', $ip, 40, 900) || RateLimiter::tooMany('login-email', $email, 8, 900)) {
            Session::withInput(['email' => $email], ['email' => t('Too many sign-in attempts. Wait 15 minutes, then try again.')]);
            return $this->redirect('/login');
        }

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('Enter the email address your school gave you.');
        }
        if ($password === '') {
            $errors['password'] = t('Enter your password.');
        }
        if ($errors) {
            Session::withInput(['email' => $email], $errors);
            return $this->redirect('/login');
        }

        $user = Db::one('SELECT * FROM users WHERE email = :e', ['e' => $email]);
        $valid = password_verify($password, (string) ($user['password_hash'] ?? self::DUMMY_HASH)) && $user !== null;

        if (!$valid || $user['status'] !== 'active') {
            RateLimiter::attempt('login-email', $email, 8, 900);
            $message = t('The email or password is not correct.');
            if ($valid && $user['status'] === 'deactivated') {
                $message = t('This account has been deactivated. Contact your school administrator if you need access.');
            }
            Session::withInput(['email' => $email], ['password' => $message]);
            return $this->redirect('/login');
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => (int) $user['id']]);
        }
        RateLimiter::clear('login-email', $email);
        Auth::login($user);
        Audit::log('auth.login', 'user', (int) $user['id'], [], $user);

        $intended = Session::pull('intended');
        $target = (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && $intended !== '/login')
            ? $intended : home_path((string) $user['role']);
        return $this->redirect($target);
    }

    /** One-click sign-in for the fictional demo school. Local machine + APP_ENV=local + APP_DEMO_LOGIN only. */
    public function demoLogin(): Response
    {
        if (!$this->demoLoginAllowed()) {
            throw HttpException::notFound();
        }
        $userId = (int) $this->request->int('user_id');
        $user = Db::one(
            "SELECT u.* FROM users u JOIN organizations o ON o.id = u.organization_id
             WHERE u.id = :id AND u.is_demo = 1 AND o.is_demo = 1 AND u.status = 'active'",
            ['id' => $userId]
        );
        if (!$user) {
            $this->flash('error', t('That demo account is not available. Reload the demo data from the setup page.'));
            return $this->redirect('/login');
        }
        Auth::login($user);
        Audit::log('auth.demo_login', 'user', (int) $user['id'], [], $user);
        return $this->redirect(home_path((string) $user['role']));
    }

    public function logout(): Response
    {
        $user = Auth::user();
        if ($user) {
            Audit::log('auth.logout', 'user', (int) $user['id'], [], $user);
        }
        Auth::logout();
        Session::flash('success', t('You have signed out.'));
        return $this->redirect('/login');
    }

    public function showInvite(string $token): Response
    {
        $row = Tokens::find($token, 'invitation');
        if (!$row || $row['status'] !== 'invited') {
            return $this->invalidLink(t('This invitation link is no longer valid'), t('Invitation links work once and expire after 14 days. Ask your school administrator for a new link.'));
        }
        if (Auth::check()) {
            Auth::logout();
        }
        return $this->view('auth/invite', [
            'title' => t('Set up your account'),
            'invitee' => $row,
            'token' => $token,
            'school' => (string) Db::value('SELECT name FROM organizations WHERE id = :o', ['o' => (int) $row['organization_id']]),
        ], 'layouts/auth');
    }

    public function acceptInvite(string $token): Response
    {
        $row = Tokens::find($token, 'invitation');
        if (!$row || $row['status'] !== 'invited') {
            return $this->invalidLink(t('This invitation link is no longer valid'), t('Ask your school administrator for a new link.'));
        }
        $data = $this->validate([
            'preferred_name' => 'nullable|string|max:80',
            'language' => 'required|in:en,fil',
            'password' => 'required|string|confirmed',
        ], ['preferred_name' => t('Name you go by'), 'password' => t('Password')]);
        $problem = Tokens::passwordProblem((string) $data['password'], (string) $row['email'], (string) $row['given_name']);
        if ($problem !== null) {
            $this->fail('password', $problem);
        }
        Db::transaction(static function () use ($row, $data): void {
            Db::update('users', [
                'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                'status' => 'active',
                'preferred_name' => $data['preferred_name'] ?: null,
                'language' => $data['language'],
                'password_changed_at' => Db::now(),
                'updated_at' => Db::now(),
            ], ['id' => (int) $row['id']]);
            Tokens::consume((int) $row['token_id']);
        });
        $user = Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $row['id']]);
        Auth::login($user);
        Audit::log('auth.invitation_accepted', 'user', (int) $row['id'], [], $user);
        $this->flash('success', t('Welcome to Sibol. Your account is ready.'));
        return $this->redirect(home_path((string) $row['role']));
    }

    public function showReset(string $token): Response
    {
        $row = Tokens::find($token, 'password_reset');
        if (!$row || $row['status'] !== 'active') {
            return $this->invalidLink(t('This password link is no longer valid'), t('Password links work once and expire after 48 hours. Ask your school administrator for a new one.'));
        }
        return $this->view('auth/reset', ['title' => t('Choose a new password'), 'person' => $row, 'token' => $token], 'layouts/auth');
    }

    public function reset(string $token): Response
    {
        $row = Tokens::find($token, 'password_reset');
        if (!$row || $row['status'] !== 'active') {
            return $this->invalidLink(t('This password link is no longer valid'), t('Ask your school administrator for a new one.'));
        }
        $data = $this->validate(['password' => 'required|string|confirmed'], ['password' => t('Password')]);
        $problem = Tokens::passwordProblem((string) $data['password'], (string) $row['email'], (string) $row['given_name']);
        if ($problem !== null) {
            $this->fail('password', $problem);
        }
        Db::transaction(static function () use ($row, $data): void {
            Db::exec(
                'UPDATE users SET password_hash = :h, password_changed_at = :now, session_version = session_version + 1, updated_at = :now2 WHERE id = :id',
                ['h' => password_hash((string) $data['password'], PASSWORD_DEFAULT), 'now' => Db::now(), 'now2' => Db::now(), 'id' => (int) $row['id']]
            );
            Tokens::consume((int) $row['token_id']);
        });
        $user = Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $row['id']]);
        Auth::login($user);
        Audit::log('auth.password_reset', 'user', (int) $row['id'], [], $user);
        $this->flash('success', t('Your password has been changed. Other devices have been signed out.'));
        return $this->redirect(home_path((string) $row['role']));
    }

    private function invalidLink(string $heading, string $body): Response
    {
        return Response::html(\Sibol\Core\View::render('auth/invalid_link', ['title' => $heading, 'heading' => $heading, 'body' => $body], 'layouts/auth'), 410);
    }

    private function singleSchoolName(): ?string
    {
        $rows = Db::all('SELECT name FROM organizations LIMIT 2');
        return count($rows) === 1 ? (string) $rows[0]['name'] : null;
    }

    private function demoLoginAllowed(): bool
    {
        return Config::isLocal() && (bool) Config::get('app.demo_login') && $this->request->isLocalhost();
    }

    /** @return list<array<string,mixed>> */
    private function demoAccounts(): array
    {
        if (!$this->demoLoginAllowed()) {
            return [];
        }
        $out = [];
        foreach (['student', 'teacher', 'admin'] as $role) {
            $row = Db::one(
                "SELECT u.id, u.given_name, u.family_name, u.preferred_name, u.role FROM users u JOIN organizations o ON o.id = u.organization_id
                 WHERE u.is_demo = 1 AND o.is_demo = 1 AND u.status = 'active' AND u.role = :r ORDER BY u.id LIMIT 1",
                ['r' => $role]
            );
            if ($row) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

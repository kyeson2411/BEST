<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\HttpException;
use Sibol\Core\RateLimiter;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Services\GoogleSignIn;
use Sibol\Services\GoogleSignInError;

/** "Sign in with Google" for existing accounts (Services/GoogleSignIn): not found until the school sets it up in .env. */
final class GoogleController extends Controller
{
    public function start(): Response
    {
        if (!GoogleSignIn::configured()) {
            throw HttpException::notFound();
        }
        $intended = Session::pull('intended');
        $to = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && $intended !== '/login' ? $intended : '';
        return Response::redirect((new GoogleSignIn())->start($to, time()), 302);
    }

    /** Google sends the person back here with a code; the account is then found by its verified school email. */
    public function callback(): Response
    {
        if (!GoogleSignIn::configured()) {
            throw HttpException::notFound();
        }
        if (!RateLimiter::attempt('google-ip', $this->request->ip(), 30, 900)) {
            $this->flash('error', t('Too many sign-in attempts. Wait 15 minutes, then try again.'));
            return $this->redirect('/login');
        }
        try {
            $result = (new GoogleSignIn())->finish($this->request->query, time());
            $user = GoogleSignIn::account($result['email']);
        } catch (GoogleSignInError $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/login');
        }
        return $this->redirect(GoogleSignIn::complete($user, $result['to']));
    }
}

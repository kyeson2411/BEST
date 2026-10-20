<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Core\ValidationException;
use Sibol\Services\Notices;
use Sibol\Services\Settings;

/**
 * "Before you start": the privacy notice and the rules for using Sibol, read and accepted on first sign-in
 * and again whenever either changes (when the school's policy asks this person to).
 */
final class NoticeController extends Controller
{
    public function show(): Response
    {
        $user = $this->user();
        $pending = Notices::pending($user);
        if (!$pending) {
            return $this->redirect(home_path((string) $user['role']));
        }
        $orgId = (int) $user['organization_id'];
        return $this->view('pages/welcome', [
            'title' => t('Before you start'),
            'pending' => $pending,
            'versions' => Notices::current($orgId),
            'renewed' => Notices::acceptedBefore($user),
            'org' => Db::one('SELECT * FROM organizations WHERE id = :o', ['o' => $orgId]),
            'privacy' => Settings::get($orgId, 'privacy'),
            'aiProvider' => (string) Config::get('ai.provider', 'demo'),
            'role' => (string) $user['role'],
        ], 'layouts/bare');
    }

    public function accept(): Response
    {
        $user = $this->user();
        $pending = Notices::pending($user);
        $errors = [];
        if (in_array('privacy', $pending, true) && !$this->request->bool('privacy')) {
            $errors['privacy'] = t('Tick this to confirm you have read the privacy notice.');
        }
        if (in_array('rules', $pending, true) && !$this->request->bool('rules')) {
            $errors['rules'] = t('Tick this to agree to the rules for using Sibol.');
        }
        if ($errors) {
            throw new ValidationException($errors, $this->request->all(), '/welcome');
        }
        Notices::acknowledge($user, $pending);
        $intended = Session::pull('intended');
        $target = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && !in_array($intended, ['/welcome', '/login'], true)
            ? $intended : home_path((string) $user['role']);
        $this->flash('success', t('Thank you. You can use Sibol now.'));
        return $this->redirect($target);
    }

    /** Reading the notices in another language before accepting them. */
    public function language(): Response
    {
        $user = $this->user();
        $language = $this->request->str('language');
        if (array_key_exists($language, (array) Config::get('app.locales'))) {
            Db::update('users', ['language' => $language, 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
        }
        return $this->redirect('/welcome');
    }
}

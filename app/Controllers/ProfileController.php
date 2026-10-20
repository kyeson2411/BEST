<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Audit;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\ImageSanitizer;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Core\ValidationException;
use Sibol\Services\EmailDigest;
use Sibol\Services\Notifier;
use Sibol\Services\Tokens;

/** Account and preferences for every role. */
final class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = $this->user();
        return $this->view('shared/settings', [
            'title' => t('Account and preferences'),
            'user' => $user,
            'prefs' => json_decode((string) ($user['preferences'] ?? ''), true) ?: [],
            'types' => Notifier::types(),
            'requests' => Db::all('SELECT * FROM privacy_requests WHERE user_id = :u ORDER BY created_at DESC LIMIT 10', ['u' => (int) $user['id']]),
            'timezones' => ['' => t('Use the school\'s timezone (:tz)', ['tz' => $user['org_timezone']])] + array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
        ]);
    }

    public function updateProfile(): Response
    {
        $user = $this->user();
        $data = $this->validate(['preferred_name' => 'nullable|string|max:80'], ['preferred_name' => t('Name you go by')], '/settings');
        Db::update('users', ['preferred_name' => $data['preferred_name'] ?: null, 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
        $this->flash('success', t('Profile saved.'));
        return $this->redirect('/settings');
    }

    public function updatePreferences(): Response
    {
        $user = $this->user();
        $locales = implode(',', array_keys((array) Config::get('app.locales')));
        $data = $this->validate([
            'language' => 'required|in:' . $locales,
            'timezone' => 'nullable|timezone',
            'large_text' => 'bool',
            'reduce_motion' => 'bool',
            'underline_links' => 'bool',
            'email_digest' => 'bool',
        ], ['timezone' => t('Timezone')], '/settings#preferences');
        $muted = array_values(array_intersect(array_keys(Notifier::types()), array_diff(array_keys(Notifier::types()), (array) $this->request->arr('notify'))));
        $prefs = json_decode((string) ($user['preferences'] ?? ''), true) ?: [];
        $prefs = array_merge($prefs, [
            'large_text' => (bool) $data['large_text'],
            'reduce_motion' => (bool) $data['reduce_motion'],
            'underline_links' => (bool) $data['underline_links'],
            'muted_notifications' => $muted,
            // Only someone who can receive it can turn the daily email on.
            'email_digest' => (bool) $data['email_digest'] && EmailDigest::available($user),
        ]);
        Db::update('users', [
            'language' => $data['language'],
            'timezone' => $data['timezone'] ?: null,
            'preferences' => json_encode($prefs),
            'updated_at' => Db::now(),
        ], ['id' => (int) $user['id']]);
        $this->flash('success', t('Preferences saved.'));
        return $this->redirect('/settings#preferences');
    }

    public function updatePassword(): Response
    {
        $user = $this->user();
        $current = (string) ($this->request->post['current_password'] ?? '');
        if (!password_verify($current, (string) $user['password_hash'])) {
            throw new ValidationException(['current_password' => t('Your current password is not correct.')], [], '/settings#password');
        }
        $data = $this->validate(['password' => 'required|string|confirmed'], ['password' => t('New password')], '/settings#password');
        $problem = Tokens::passwordProblem((string) $data['password'], $user['email'], (string) $user['given_name'], $user['username']);
        if ($problem !== null) {
            $this->fail('password', $problem, '/settings#password');
        }
        Db::exec(
            'UPDATE users SET password_hash = :h, password_changed_at = :n, session_version = session_version + 1, updated_at = :n2 WHERE id = :id',
            ['h' => password_hash((string) $data['password'], PASSWORD_DEFAULT), 'n' => Db::now(), 'n2' => Db::now(), 'id' => (int) $user['id']]
        );
        $_SESSION['sv'] = (int) $user['session_version'] + 1;
        Audit::log('auth.password_changed', 'user', (int) $user['id']);
        $this->flash('success', t('Password changed. Other devices have been signed out.'));
        return $this->redirect('/settings#password');
    }

    public function updateAvatar(): Response
    {
        $user = $this->user();
        $file = $this->request->file('avatar');
        $problem = Uploads::validate($file, ['jpg', 'jpeg', 'png'], 2 * 1048576);
        if ($problem !== null) {
            $this->fail('avatar', $problem, '/settings');
        }
        $stored = Uploads::store($file, 'avatars');
        if (!ImageSanitizer::clean(Uploads::absolute($stored['path']), $stored['ext'])) {
            Uploads::delete($stored['path']);
            $this->fail('avatar', t('That image could not be read. Try saving it again as a JPG or PNG.'), '/settings');
        }
        Uploads::delete($user['avatar_path'] ?? null);
        Db::update('users', ['avatar_path' => $stored['path'], 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
        $this->flash('success', t('Photo updated. Location and camera details were removed from the file.'));
        return $this->redirect('/settings');
    }

    public function removeAvatar(): Response
    {
        $user = $this->user();
        Uploads::delete($user['avatar_path'] ?? null);
        Db::update('users', ['avatar_path' => null, 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
        $this->flash('success', t('Photo removed.'));
        return $this->redirect('/settings');
    }

    public function signOutOthers(): Response
    {
        $user = $this->user();
        Db::exec('UPDATE users SET session_version = session_version + 1 WHERE id = :id', ['id' => (int) $user['id']]);
        $_SESSION['sv'] = (int) $user['session_version'] + 1;
        Audit::log('auth.sessions_revoked', 'user', (int) $user['id']);
        $this->flash('success', t('You have been signed out everywhere else.'));
        return $this->redirect('/settings#sessions');
    }

    public function requestPrivacy(): Response
    {
        $user = $this->user();
        $data = $this->validate([
            'request_type' => 'required|in:export,correction,deletion',
            'details' => 'nullable|string|max:2000',
        ], ['request_type' => t('Type of request'), 'details' => t('Details')], '/settings#privacy');
        if ($data['request_type'] !== 'export' && trim((string) $data['details']) === '') {
            $this->fail('details', t('Describe what should be corrected or deleted.'), '/settings#privacy');
        }
        $open = (int) Db::value(
            "SELECT COUNT(*) FROM privacy_requests WHERE user_id = :u AND request_type = :t AND status IN ('submitted','in_review')",
            ['u' => (int) $user['id'], 't' => $data['request_type']]
        );
        if ($open) {
            $this->flash('info', t('You already have an open request of this type. The school will respond to it first.'));
            return $this->redirect('/settings#privacy');
        }
        $id = Db::insert('privacy_requests', [
            'organization_id' => (int) $user['organization_id'],
            'user_id' => (int) $user['id'],
            'request_type' => $data['request_type'],
            'details' => $data['details'],
            'status' => 'submitted',
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        $admins = array_map('intval', Db::column("SELECT id FROM users WHERE organization_id = :o AND role = 'admin' AND status = 'active'", ['o' => (int) $user['organization_id']]));
        Notifier::send((int) $user['organization_id'], $admins, 'privacy', t('New privacy request'), t('Type: :type', ['type' => $data['request_type']]), '/admin/privacy/requests/' . $id);
        Audit::log('privacy.request_submitted', 'privacy_request', $id, ['type' => $data['request_type']]);
        $this->flash('success', t('Request sent. The school will review it and you will get a notification when it is resolved.'));
        return $this->redirect('/settings#privacy');
    }

    public function downloadExport(int $id): Response
    {
        $user = $this->user();
        $request = Db::one(
            "SELECT * FROM privacy_requests WHERE id = :id AND user_id = :u AND request_type = 'export' AND status = 'completed' AND export_path IS NOT NULL",
            ['id' => $id, 'u' => (int) $user['id']]
        );
        if (!$request || ($request['export_expires_at'] !== null && $request['export_expires_at'] < Db::now())) {
            throw new HttpException(404, t('This export is not available. Exports can be downloaded for 14 days; ask for a new one if it has expired.'));
        }
        Audit::log('privacy.export_downloaded', 'privacy_request', $id);
        return Response::file(Uploads::absolute((string) $request['export_path']), 'sibol-my-data-' . gmdate('Y-m-d') . '.json', 'application/json; charset=utf-8');
    }
}

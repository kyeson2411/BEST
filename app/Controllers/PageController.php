<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Core\Translator;
use Sibol\Services\Navigation;
use Sibol\Services\Settings;

final class PageController extends Controller
{
    /** Signed-in people go to their home; visitors see the school's sign-in page at the site root. */
    public function home(): Response
    {
        if (Auth::check()) {
            return $this->redirect(home_path());
        }
        return (new AuthController($this->request))->showLogin();
    }

    /** Plain-language privacy notice. Uses the school's own settings when known. */
    public function privacy(): Response
    {
        $user = Auth::user();
        $org = $user
            ? Db::one('SELECT * FROM organizations WHERE id = :o', ['o' => (int) $user['organization_id']])
            : (count($all = Db::all('SELECT * FROM organizations LIMIT 2')) === 1 ? $all[0] : null);
        $privacy = $org ? Settings::get((int) $org['id'], 'privacy') : Settings::defaults()['privacy'];
        $data = [
            'title' => t('Privacy notice'),
            'org' => $org,
            'privacy' => $privacy,
            'aiProvider' => (string) Config::get('ai.provider', 'demo'),
        ];
        return $this->view('pages/privacy', $data, $user ? 'layouts/app' : 'layouts/bare');
    }

    /** The rules for using Sibol: academic honesty and what the companion will and won't do. */
    public function rules(): Response
    {
        $user = Auth::user();
        $privacy = $user ? Settings::get((int) $user['organization_id'], 'privacy') : Settings::defaults()['privacy'];
        return $this->view('pages/rules', [
            'title' => t('Rules for using B.E.S.T. Learning and Interactive Study System'),
            'role' => $user['role'] ?? null,
            'privacy' => $privacy,
            'version' => $user ? \Sibol\Services\Notices::current((int) $user['organization_id'])['rules'] : \Sibol\Services\Notices::VERSIONS['rules'],
        ], $user ? 'layouts/app' : 'layouts/bare');
    }

    public function help(): Response
    {
        $user = Auth::user();
        $privacy = $user ? Settings::get((int) $user['organization_id'], 'privacy') : Settings::defaults()['privacy'];
        return $this->view('pages/help', [
            'title' => t('Help'),
            'role' => $user['role'] ?? null,
            'privacy' => $privacy,
            'aiDemo' => Config::get('ai.provider', 'demo') === 'demo',
        ], $user ? 'layouts/app' : 'layouts/bare');
    }

    /** The web app manifest, so phones and computers can install Sibol with the school's seal as its icon. */
    public function manifest(): Response
    {
        $app = (string) Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System');
        $school = trim((string) Config::get('school.name', ''));
        $base = base_path_url();
        $response = Response::json([
            'id' => $base . '/',
            'name' => $school !== '' ? $app . ' – ' . $school : $app,
            'short_name' => (string) Config::get('app.short_name', 'B.E.S.T.'),
            'description' => t('Sign in to B.E.S.T. Learning and Interactive Study System, the learning companion of :school.', ['school' => $school !== '' ? $school : $app]),
            'lang' => Translator::locale() === 'fil' ? 'fil' : 'en',
            'start_url' => $base . '/',
            'scope' => $base . '/',
            'display' => 'standalone',
            // The paper and forest colours of the design system.
            'background_color' => '#F6F5EE',
            'theme_color' => '#1E3D31',
            'icons' => [
                ['src' => asset('school/app-icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('school/app-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                // A small maskable icon too, so phones checking whether Sibol can be installed fetch 41 KB, not 228.
                ['src' => asset('school/app-icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('school/app-icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ]);
        // Kept for a day by the browser only: the description follows the person's language.
        return $response->header('Content-Type', 'application/manifest+json; charset=utf-8')->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * What the service worker shows when there is no connection. The same for everyone (no names, no messages, no
     * form token): the list of materials saved on this device comes from the device itself (offline.js).
     */
    public function offline(): Response
    {
        return $this->view('pages/offline', ['title' => t('You are offline'), 'offline_page' => true, 'scripts' => ['offline.js', 'practice-offline.js']], 'layouts/bare');
    }

    /** Full navigation for small screens without JavaScript. */
    public function menu(): Response
    {
        $user = $this->user();
        return $this->view('pages/menu', [
            'title' => t('Menu'),
            'items' => Navigation::items($user),
            'footer' => Navigation::footer($user),
            'from' => $this->request->str('from'),
        ]);
    }
}

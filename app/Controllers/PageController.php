<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Response;
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
            'title' => t('Rules for using Sibol'),
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

    /** Full navigation for small screens without JavaScript. */
    public function menu(): Response
    {
        $user = $this->user();
        return $this->view('pages/menu', [
            'title' => t('Menu'),
            'items' => Navigation::items($user),
            'footer' => Navigation::footer(),
        ]);
    }
}

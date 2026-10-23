<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Logger;
use Sibol\Core\Response;
use Sibol\Services\DemoSeeder;
use Sibol\Services\Migrator;
use Sibol\Services\Settings;
use Sibol\Services\SystemCheck;

/**
 * First-run helper for XAMPP: create tables and load the fictional demo school
 * without a terminal. Only available with APP_ENV=local, from this computer.
 */
final class SetupController extends Controller
{
    public function index(): Response
    {
        $this->guard();
        $dbOk = Migrator::databaseExists();
        $pending = [];
        $hasData = false;
        $error = null;
        try {
            if ($dbOk) {
                $pending = Migrator::pending();
                $hasData = $pending === [] && (int) Db::value('SELECT COUNT(*) FROM organizations') > 0;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        return $this->view('setup/index', [
            'title' => t('Set up B.E.S.T. Learning and Interactive Study System'),
            'dbOk' => $dbOk,
            'pending' => $pending,
            'hasData' => $hasData,
            'error' => $error,
            'checks' => SystemCheck::run(),
            'dbName' => (string) Config::get('db.name'),
        ], 'layouts/bare');
    }

    public function migrate(): Response
    {
        $this->guard();
        try {
            Migrator::ensureDatabase();
            Db::setPdo(null);
            $applied = Migrator::migrate();
            $this->flash('success', $applied ? t('Created the database tables.') : t('The database tables were already up to date.'));
        } catch (\Throwable $e) {
            Logger::error('Setup migrate failed', ['error' => $e->getMessage()]);
            $this->flash('error', t('The tables could not be created: :error', ['error' => $e->getMessage()]));
        }
        return $this->redirect('/setup');
    }

    public function seed(): Response
    {
        $this->guard();
        try {
            if ((int) Db::value('SELECT COUNT(*) FROM organizations') > 0) {
                $this->flash('error', t('This database already has a school. Use “Reset demo data” to start again.'));
                return $this->redirect('/setup');
            }
            (new DemoSeeder())->run();
            $this->flash('success', t('Loaded the fictional demo school. Sign in with one of the demo accounts.'));
            return $this->redirect('/login');
        } catch (\Throwable $e) {
            Logger::error('Setup seed failed', ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $this->flash('error', t('The demo data could not be loaded: :error', ['error' => $e->getMessage()]));
            return $this->redirect('/setup');
        }
    }

    public function reset(): Response
    {
        $this->guard();
        try {
            Migrator::dropAll();
            Migrator::clearUploads();
            Migrator::migrate();
            Settings::flush();
            (new DemoSeeder())->run();
            \Sibol\Core\Auth::logout();
            $this->flash('success', t('The demo school was reset to its starting state.'));
            return $this->redirect('/login');
        } catch (\Throwable $e) {
            Logger::error('Setup reset failed', ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $this->flash('error', t('The reset did not finish: :error', ['error' => $e->getMessage()]));
            return $this->redirect('/setup');
        }
    }

    private function guard(): void
    {
        if (!Config::isLocal() || !$this->request->isLocalhost()) {
            throw HttpException::notFound();
        }
    }
}

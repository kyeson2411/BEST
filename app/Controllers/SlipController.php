<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Response;
use Sibol\Services\SignInSlips;

/** The printable page of sign-in slips that this person issued in the last 30 minutes. */
final class SlipController extends Controller
{
    public function show(): Response
    {
        $user = $this->user();
        $data = SignInSlips::current();
        if (!$data) {
            $this->flash('info', t('Those slips are no longer shown here. Codes on slips you already printed keep working until they are used or expire; print new ones if you need them.'));
            return $this->redirect(home_path());
        }
        $groups = [];
        foreach ($data['slips'] as $slip) {
            $groups[(string) $slip['group']][] = $slip;
        }
        return $this->view('shared/slips', [
            'title' => t('Sign-in slips'),
            'heading' => (string) $data['title'],
            'groups' => $groups,
            'count' => count($data['slips']),
            'back' => SignInSlips::backPath((string) $data['back'], (string) $user['role']),
            'keptUntil' => gmdate('Y-m-d H:i:s', (int) $data['at'] + SignInSlips::KEEP_SECONDS),
            'page_class' => 'page--slips',
        ]);
    }
}

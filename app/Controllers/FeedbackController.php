<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\RateLimiter;
use Sibol\Core\Response;
use Sibol\Services\SiteFeedback;

/**
 * "Send feedback": students and teachers tell the school's administrators that something in Sibol is
 * broken or confusing, or share an idea, and see the status and any reply. It is not a chat.
 */
final class FeedbackController extends Controller
{
    public function create(): Response
    {
        $user = $this->user();
        return $this->view('shared/feedback', [
            'title' => t('Send feedback'),
            'from' => SiteFeedback::cleanPath($this->request->str('from')),
            'mine' => SiteFeedback::mine((int) $user['id']),
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $from = SiteFeedback::cleanPath($this->request->str('from'));
        $back = '/feedback' . ($from !== null ? '?' . http_build_query(['from' => $from]) : '');
        $data = $this->validate([
            'category' => 'required|in:' . implode(',', SiteFeedback::CATEGORIES),
            'body' => 'required|string|max:' . SiteFeedback::MAX_TEXT,
            'include_page' => 'bool',
        ], ['category' => t('What it is about'), 'body' => t('Your feedback')], $back);
        if (!RateLimiter::attempt('site-feedback', (string) $user['id'], 10, 3600)) {
            $this->fail('body', t('You have sent a lot of feedback in the last hour. Try again later.'), $back);
        }
        SiteFeedback::send($user, (string) $data['category'], (string) $data['body'], $data['include_page'] ? $from : null);
        $this->flash('success', t('Thank you. Your school\'s administrators will read it.'));
        return $this->redirect('/feedback');
    }
}

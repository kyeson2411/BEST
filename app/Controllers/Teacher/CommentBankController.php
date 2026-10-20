<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Response;
use Sibol\Services\CommentBank;

/** A teacher's own comment bank: saved feedback comments to insert while grading. */
final class CommentBankController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $editing = $this->request->int('edit');
        if ($editing !== null) {
            CommentBank::own($user, $editing);
        }
        return $this->view('teacher/comment_bank', [
            'title' => t('Comment bank'),
            'comments' => CommentBank::forTeacher((int) $user['id']),
            'criteria' => CommentBank::criterionTitles($user),
            'editing' => $editing,
        ]);
    }

    /** Adds a comment from this page, or from the grading page's script (which gets JSON back). */
    public function store(): Response
    {
        $user = $this->user();
        $body = $this->request->str('body');
        $criterion = $this->request->str('criterion');
        $problems = CommentBank::problems($body, $criterion);
        if ($this->request->wantsJson()) {
            if ($problems) {
                return $this->json(['error' => reset($problems)], 422);
            }
            $id = CommentBank::add($user, $body, $criterion);
            return $this->json(['id' => $id, 'body' => trim($body), 'criterion' => trim($criterion)]);
        }
        foreach ($problems as $field => $message) {
            $this->fail($field, $message, '/teacher/comments');
        }
        CommentBank::add($user, $body, $criterion);
        $this->flash('success', t('Comment saved to your bank.'));
        return $this->redirect('/teacher/comments');
    }

    public function update(int $id): Response
    {
        $user = $this->user();
        CommentBank::own($user, $id);
        $body = $this->request->str('body');
        $criterion = $this->request->str('criterion');
        foreach (CommentBank::problems($body, $criterion) as $field => $message) {
            $this->fail($field, $message, '/teacher/comments?edit=' . $id);
        }
        CommentBank::update($user, $id, $body, $criterion);
        $this->flash('success', t('Comment updated.'));
        return $this->redirect('/teacher/comments');
    }

    public function destroy(int $id): Response
    {
        CommentBank::delete($this->user(), $id);
        $this->flash('success', t('Comment removed from your bank.'));
        return $this->redirect('/teacher/comments');
    }

    /** The grading page's script counts each insert, so the most used comments come first. */
    public function used(int $id): Response
    {
        CommentBank::used($this->user(), $id);
        return $this->json(['ok' => true]);
    }
}

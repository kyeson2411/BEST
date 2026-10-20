<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Interface text for the scripts in public/assets/js, which carry no English of their own.
 * Views pass these to the page in data-i18n attributes (json_attr()): common() on <body> in every
 * layout, the others on the root element of the script that uses them. Scripts read them with
 * Sibol.strings(element) and look keys up with S('key', {params}).
 *
 * Placeholders such as :count stay in the text and are filled in by the script. A [singular, plural]
 * pair is chosen by params.count exactly as tp() chooses on the server.
 */
final class ScriptStrings
{
    /** app.js: toasts, confirmations, busy buttons, copy buttons, counters, notifications. */
    public static function common(): array
    {
        return [
            'dismiss' => t('Dismiss message'),
            'confirm_title' => t('Are you sure?'),
            'confirm_action' => t('Confirm'),
            'busy' => t('Saving…'),
            'copied' => t('Copied'),
            'chars_left' => [t(':count character left'), t(':count characters left')],
            'time_low' => t('Two minutes left. Your answers save when you submit.'),
            'notifications_failed' => t('Notifications could not load. Open the full list instead.'),
        ];
    }

    /** quiz.js: the answered counter and autosave status on a quiz attempt. */
    public static function quiz(): array
    {
        return [
            'answered' => [t(':count of :total answered'), t(':count of :total answered')],
            'saved_at' => t('saved :time'),
            'not_saved' => t('not saved yet'),
            'offline' => t('offline, will retry'),
            'unsaved' => t('unsaved changes'),
        ];
    }

    /** companion.js: streamed answers, their progress and errors. */
    public static function companion(): array
    {
        return [
            'you' => t('You'),
            'assistant' => t('Sibol'),
            'thinking' => t('Thinking'),
            'reading' => [t('Reading :count passage from your materials'), t('Reading :count passages from your materials')],
            'answering' => t(':name is answering.', ['name' => t('Sibol')]),
            'ready' => t('Answer ready.'),
            'try_again' => t('Try again'),
            'failed' => t('Something went wrong. Try again.'),
            'request_failed' => t('The request could not be completed.'),
            'request_failed_status' => t('The request could not be completed (error :status).'),
            'interrupted' => t('The answer was interrupted. Try again.'),
            'stopped' => t('Stopped. What was written so far has been saved.'),
            'connection_lost' => t('The connection was lost. Check your internet connection and try again.'),
            'copy_selected' => t('Text selected. Press Ctrl+C to copy.'),
        ];
    }

    /** practice.js: flashcard decks. */
    public static function flashcards(): array
    {
        return [
            'card' => t('Card :n of :total'),
            'front' => t('Front'),
            'back' => t('Back'),
            'done' => t('You knew :score of :total cards.'),
        ];
    }

    /** materials.js: background processing of a new upload. */
    public static function material(): array
    {
        return [
            'indexed' => [t(':count passage indexed'), t(':count passages indexed')],
            'working' => t('Working…'),
            'start_failed' => t('Could not start processing. Reload the page to try again.'),
        ];
    }

    /** grading.js: copying an AI grading suggestion into the form. */
    public static function grading(): array
    {
        return [
            'suggestion_copied' => t('Suggestion copied into the form. Check it, then save.'),
        ];
    }
}

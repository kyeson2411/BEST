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
            'time_low' => t('Two minutes left. Your answers are saved as you go.'),
            'notifications_failed' => t('Notifications could not load. Open the full list instead.'),
            'notifications' => t('Notifications'),
            'notifications_unread' => [t('Notifications, :count unread'), t('Notifications, :count unread')],
        ];
    }

    /** quiz.js: the answered counter, save status and connection notices on a quiz attempt. */
    public static function quiz(): array
    {
        return [
            'answered' => [t(':count of :total answered'), t(':count of :total answered')],
            'saving' => t('saving…'),
            'saved_at' => t('saved :time'),
            'not_saved' => t('not saved yet'),
            'kept' => t('kept on this device'),
            'unsaved' => t('unsaved changes'),
            'offline_notice' => t('You are offline. Your answers are kept on this device and will be sent when you reconnect. Keep this page open.'),
            'back_online' => t('Back online. Sending your answers…'),
            'restored' => t('Answers you gave earlier on this device were restored and are being sent.'),
            'submit_offline' => t('You are offline, so the quiz was not submitted yet. Your answers are kept on this device. Submit again when you are back online.'),
            'time_up_offline' => t('Time is up. Reconnect to finish: the quiz will be submitted with the answers that reached the server in time.'),
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

    /** grading.js: copying an AI grading suggestion into the form, and the comment bank. */
    public static function grading(): array
    {
        return [
            'suggestion_copied' => t('Suggestion copied into the form. Check it, then save.'),
            'bank_saved' => t('Saved to your comment bank.'),
            'bank_failed' => t('Could not save the comment. Try again.'),
            'bank_empty' => t('Write a comment first, or select the words to save.'),
        ];
    }

    /** offline.js: keeping class files on this device for reading without a connection. */
    public static function offline(): array
    {
        return [
            'kept' => t('“:title” is kept on this device. It opens without a connection until you sign out.'),
            'released' => t('“:title” is no longer kept on this device.'),
            'keep_failed' => t('The file could not be kept on this device. Check the connection and the free space, then try again.'),
            'release_failed' => t('The file could not be removed from this device. Try again.'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Config;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\Logger;
use Sibol\Core\Markdown;
use Sibol\Core\Translator;
use Sibol\Core\View;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\MaterialGaps;
use Sibol\Services\Settings;

/**
 * One companion turn: limits → permission-filtered retrieval → prompt → model (or demo)
 * → honest citations → persistence and usage accounting. Events are streamed to the
 * browser as NDJSON: meta, delta, done, error.
 */
final class Companion
{
    public const HISTORY_MESSAGES = 12;

    /**
     * @param callable(array):void $emit
     * @param callable():bool $aborted
     */
    public static function turn(array $user, array $conversation, string $question, callable $emit, callable $aborted, bool $regenerate = false): void
    {
        $blocked = Usage::blockedReason($user, 'companion');
        if ($blocked !== null) {
            $emit(['type' => 'error', 'code' => 'limit', 'message' => $blocked]);
            return;
        }
        try {
            $provider = Providers::make();
        } catch (AiException $e) {
            $emit(['type' => 'error', 'code' => $e->errorCode, 'message' => $e->getMessage()]);
            return;
        }

        $conversationId = (int) $conversation['id'];
        if (!$regenerate) {
            Db::insert('ai_messages', [
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => $question,
                'status' => 'complete',
                'created_at' => Db::now(),
            ]);
        }

        $mode = (string) $conversation['mode'];
        $sources = self::gatherSources($user, $conversation, $question);
        $upcoming = ($mode === 'study_plan' && $user['role'] === 'student') ? StudentRepo::dueSoon($user, 14, 0, 6) : [];
        // Questions quoted from the student's own graded work whose answers are not out yet: hints, never the answer.
        $graded = GradedWork::matches($user, array_merge([$question], array_map('strval', Db::column(
            "SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'user' ORDER BY id DESC LIMIT 6",
            ['c' => $conversationId]
        ))));
        $language = Language::answerLanguage((string) ($conversation['language'] ?? 'en'), $question);
        $emit(['type' => 'meta', 'sources' => count($sources), 'demo' => $provider->isDemo()]);

        $result = new AiResult();
        $error = null;
        // With a live model, Sibol itself says first when the class materials do not cover the question, so it never
        // depends on the model doing so (demo answers say it in their own words). Graded work gets its own line.
        $notice = $provider->isDemo() || $graded ? null : self::coverageNotice($user, $conversation, $question, $sources);
        $gradedNotice = !$provider->isDemo() && $graded ? GradedWork::notice($graded, $language) : null;
        $gradedFallback = false;
        foreach ([$notice, $gradedNotice] as $line) {
            if ($line !== null) {
                $emit(['type' => 'delta', 'text' => $line . "\n\n"]);
            }
        }
        try {
            if ($provider->isDemo()) {
                $started = microtime(true);
                $text = DemoResponder::respond($user, $conversation, $question, $sources, $upcoming, $graded);
                foreach (preg_split('/(?<=\s)/u', $text) ?: [] as $i => $piece) {
                    if ($aborted()) {
                        $result->cancelled = true;
                        break;
                    }
                    $result->text .= $piece;
                    $emit(['type' => 'delta', 'text' => $piece]);
                    if ($i % 4 === 0) {
                        usleep(8000);
                    }
                }
                $result->model = $provider->model();
                $result->latencyMs = (int) round((microtime(true) - $started) * 1000);
            } else {
                $request = static fn (string $note): AiRequest => new AiRequest(
                    Prompts::companionSystem($user, $mode, ['language' => $conversation['language'], 'level' => $conversation['level'] ?? '']),
                    self::messages($conversationId, $question, $sources, $conversation, $user, $upcoming, $notice !== null, $note),
                    'companion',
                    16000,
                    (string) Config::get('ai.effort', 'medium')
                );
                if ($graded) {
                    // Not streamed: the reply is checked against the answer keys before the student sees any of it.
                    $result = $provider->complete($request(GradedWork::instruction($graded)), null, $aborted);
                    if (self::givesAway($result, $graded)) {
                        $first = $result;
                        try {
                            $result = $provider->complete($request(GradedWork::instruction($graded) . "\n\n" . GradedWork::retryInstruction()), null, $aborted);
                            $result->inputTokens += $first->inputTokens;
                            $result->outputTokens += $first->outputTokens;
                            $result->latencyMs += $first->latencyMs;
                        } catch (AiException $e) {
                            $result = $first;
                        }
                        if (self::givesAway($result, $graded)) {
                            $result->text = GradedWork::fallback($graded, $language);
                            $gradedFallback = true;
                        }
                    }
                } else {
                    $result = $provider->complete($request(''), static function (string $t) use ($emit): void {
                        $emit(['type' => 'delta', 'text' => $t]);
                    }, $aborted);
                }
            }
        } catch (AiException $e) {
            $error = $e;
        } catch (\Throwable $e) {
            Logger::error('Companion turn failed', ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $error = new AiException('server_error', t('Something went wrong while preparing the answer. Try again.'), true);
        }

        if ($error !== null) {
            $messageId = Db::insert('ai_messages', [
                'conversation_id' => $conversationId, 'role' => 'assistant', 'mode' => $mode, 'content' => $error->getMessage(),
                'status' => 'error', 'provider' => $provider->name(), 'model' => $provider->model(),
                'is_demo' => $provider->isDemo() ? 1 : 0, 'error_code' => $error->errorCode, 'created_at' => Db::now(),
            ]);
            Usage::record($user, 'companion', $provider, 'error', 0, 0, null, $error->errorCode);
            self::touch($conversation, $question);
            $emit(['type' => 'error', 'code' => $error->errorCode, 'message' => $error->getMessage(), 'message_id' => $messageId, 'retryable' => $error->retryable]);
            return;
        }

        $status = 'complete';
        $errorCode = $result->errorCode;
        if ($result->refused) {
            $text = t('I can\'t help with that request. If you think this is a mistake, try asking in a different way, or ask your teacher.');
            $citations = [];
            $errorCode = 'refused';
        } else {
            $resolved = Citations::resolve($result->text, $sources);
            $text = $resolved['text'];
            $citations = $resolved['citations'];
            if ($result->cancelled) {
                $status = 'cancelled';
            } elseif ($result->errorCode !== null) {
                $status = 'error';
            } elseif ($result->truncated) {
                $text .= "\n\n" . t('(This answer was cut short. Ask me to continue.)');
            }
        }
        if (trim($text) === '' && $status !== 'complete') {
            $text = $status === 'cancelled' ? t('Stopped before any answer was written.') : t('The answer was interrupted. Try again.');
        }
        if ($notice !== null && !$result->refused) {
            $text = $notice . "\n\n" . $text;
        }
        if ($gradedNotice !== null && !$result->refused && !$gradedFallback) {
            $text = $gradedNotice . "\n\n" . $text;
        }
        // Sibol's own graded-work message is no answer about the materials, so it gets no grounding label.
        $grounding = $provider->isDemo() ? 'demo' : ($gradedFallback ? null : ($citations ? 'sources' : ($sources ? 'no_sources' : 'general')));

        $messageId = Db::transaction(static function () use ($conversationId, $mode, $text, $status, $grounding, $provider, $result, $errorCode, $citations): int {
            $id = Db::insert('ai_messages', [
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'mode' => $mode,
                'content' => $text,
                'status' => $status,
                'grounding' => $grounding,
                'provider' => $provider->name(),
                'model' => $result->model ?: $provider->model(),
                'is_demo' => $provider->isDemo() ? 1 : 0,
                'input_tokens' => $result->inputTokens ?: null,
                'output_tokens' => $result->outputTokens ?: null,
                'latency_ms' => $result->latencyMs ?: null,
                'error_code' => $errorCode,
                'created_at' => Db::now(),
            ]);
            foreach ($citations as $c) {
                Db::insert('ai_citations', [
                    'message_id' => $id,
                    'ref_no' => $c['ref_no'],
                    'material_id' => $c['material_id'],
                    'material_version_id' => $c['material_version_id'],
                    'chunk_id' => $c['chunk_id'],
                    'title' => mb_substr($c['title'], 0, 200),
                    'page_from' => $c['page_from'],
                    'page_to' => $c['page_to'],
                    'section_title' => $c['section_title'] !== null ? mb_substr((string) $c['section_title'], 0, 255) : null,
                    'excerpt' => $c['excerpt'],
                    'created_at' => Db::now(),
                ]);
            }
            return $id;
        });
        Usage::record($user, 'companion', $provider, $status === 'cancelled' ? 'cancelled' : ($status === 'error' ? 'error' : 'ok'), $result->inputTokens, $result->outputTokens, $result->latencyMs, $errorCode, $result->model ?: null);
        self::touch($conversation, $question);
        if ($status === 'complete' && !$result->refused && !$regenerate) {
            MaterialGaps::record($user, $conversation, $question, $sources);
        }

        $message = self::loadMessages($conversationId, $messageId)[0] ?? null;
        $remaining = Usage::remainingToday($user);
        $emit([
            'type' => 'done',
            'message_id' => $messageId,
            'status' => $status,
            'title' => (string) Db::value('SELECT title FROM ai_conversations WHERE id = :id', ['id' => $conversationId]),
            'remaining' => $remaining !== null ? tp(':count message left today', ':count messages left today', $remaining) : null,
            'html' => $message ? View::file('companion/_exchange_answer', ['message' => $message, 'user' => $user, 'isLast' => true, 'conversation' => $conversation]) : '',
        ]);
    }

    /**
     * The sentence that opens an answer when the class materials do not cover the question: no passage matched, or
     * the passages share fewer than half of its key words. In the answer's language. Null when they do cover it, for
     * study plans and summaries of a chosen material, and for messages that name no topic ("hi", "thanks", "1+1"),
     * which are not questions about the materials.
     */
    public static function coverageNotice(array $user, array $conversation, string $question, array $sources): ?string
    {
        $mode = (string) $conversation['mode'];
        if (in_array($mode, ['study_plan', 'summarize'], true) || Retrieval::namesNoTopic($question)) {
            return null;
        }
        $none = $sources === [];
        if (!$none && !Retrieval::partialMatch($question, $sources)) {
            return null;
        }
        $general = (bool) Settings::get((int) $user['organization_id'], 'ai.general_answers', true) && $mode !== 'ask_materials';
        $language = Language::answerLanguage((string) ($conversation['language'] ?? 'en'), $question);
        $teacher = ($user['role'] ?? '') === 'teacher';
        return Translator::with($language, static function () use ($none, $general, $teacher): string {
            if (!$none) {
                return '**' . t('Your class materials may not cover this directly.') . '** ' . t('The passages found only partly match the question.');
            }
            if (!$general) {
                return '**' . t('Your class materials don\'t cover this.') . '** ' . t('Try different key words, or ask your teacher.');
            }
            return '**' . t('Your class materials don\'t cover this.') . '** ' . ($teacher
                ? t('Anything below is general knowledge, not from the class materials: check it before you use it.')
                : t('Anything below is general knowledge, not from your teacher\'s materials, so check it with your teacher.'));
        });
    }

    /** @return list<array<string,mixed>> */
    public static function gatherSources(array $user, array $conversation, string $question): array
    {
        $mode = (string) $conversation['mode'];
        $orgId = (int) $user['organization_id'];
        if ($mode === 'study_plan') {
            return [];
        }
        if ($user['role'] === 'student' && !Settings::get($orgId, 'features.document_answers', true)) {
            return [];
        }
        $classId = $conversation['class_id'] !== null ? (int) $conversation['class_id'] : null;
        $materialId = $conversation['material_id'] !== null ? (int) $conversation['material_id'] : null;
        if ($mode === 'summarize' && $materialId !== null) {
            return Retrieval::materialPassages($user, $materialId);
        }
        // Follow-up questions ("why is that?") retrieve better with the previous question attached.
        $previous = (string) Db::value(
            "SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'user' ORDER BY id DESC LIMIT 1 OFFSET 1",
            ['c' => (int) $conversation['id']]
        );
        $query = mb_strlen($question) < 60 && $previous !== '' ? $previous . ' ' . $question : $question;
        $sources = Retrieval::search($user, $query, $classId, $materialId);
        if (!$sources && $materialId !== null) {
            // A focused material always provides context, even when no words match.
            $sources = array_slice(Retrieval::materialPassages($user, $materialId, 6000), 0, 4);
        }
        return $sources;
    }

    /** Whether a finished reply gives away the answer to any of the quoted graded questions. */
    private static function givesAway(AiResult $result, array $graded): bool
    {
        if ($result->refused || $result->cancelled) {
            return false;
        }
        foreach ($graded as $q) {
            if (GradedWork::givesAway($result->text, $q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * History plus the new question with its sources and context. $noticeShown: the answer already opens with
     * coverageNotice(), so the model is told not to say it again. $note: what the model must know about graded
     * work the question quotes (GradedWork::instruction()).
     * @return list<array{role:string,content:string}>
     */
    private static function messages(int $conversationId, string $question, array $sources, array $conversation, array $user, array $upcoming, bool $noticeShown = false, string $note = ''): array
    {
        $rows = Db::all(
            "SELECT role, content FROM ai_messages WHERE conversation_id = :c AND status = 'complete' ORDER BY id DESC LIMIT " . (self::HISTORY_MESSAGES + 1),
            ['c' => $conversationId]
        );
        $rows = array_reverse($rows);
        // The newest user row is the question being answered now; it is rebuilt below with context.
        if ($rows && $rows[count($rows) - 1]['role'] === 'user') {
            array_pop($rows);
        }
        $messages = array_map(static fn ($r) => ['role' => (string) $r['role'], 'content' => (string) $r['content']], $rows);

        $context = [];
        $context[] = 'Today is ' . Dates::now()->format('l, Y-m-d H:i') . ' (' . Dates::tz() . ').';
        if (!empty($conversation['class_name'])) {
            $context[] = 'Class: ' . $conversation['class_name'] . '.';
        }
        if (!empty($conversation['material_title'])) {
            $context[] = 'Selected material: ' . $conversation['material_title'] . '.';
        }
        if ($upcoming) {
            $lines = array_map(static fn ($u) => '- ' . $u['title'] . ' (' . $u['class_name'] . '), due ' . Dates::format((string) $u['due_at'], 'datetime'), $upcoming);
            $context[] = "The student's upcoming work:\n" . implode("\n", $lines);
        }
        $block = Prompts::sourceBlock($sources);
        if ($block === '' && $conversation['mode'] !== 'study_plan' && Retrieval::namesNoTopic($question)) {
            // A greeting or "1+1" is not a question about the materials, so it gets no "don't cover this" line from anyone.
            $block = '(This message names no topic from the class materials, for example a greeting, thanks or a quick sum: reply briefly and ask what they would like to work on. Do not say that the class materials do not cover it.)';
        } elseif ($block === '') {
            $block = $conversation['mode'] === 'study_plan' ? '' : '(No class material passages matched this question.'
                . ($noticeShown ? ' The app has already told the reader that the class materials do not cover it, so do not say that again.' : '') . ')';
        } elseif ($noticeShown) {
            $block .= "\n\n(These passages only partly match the question. The app has already told the reader so, so do not say that again: use the passages where they apply.)";
        }
        $content = ($block !== '' ? $block . "\n\n" : '')
            . implode("\n", $context) . "\n\n"
            . ($note !== '' ? $note . "\n\n" : '')
            . ($user['role'] === 'teacher' ? "Teacher's request:\n" : "Student's question:\n") . $question;
        $messages[] = ['role' => 'user', 'content' => $content];
        return $messages;
    }

    private static function touch(array $conversation, string $question): void
    {
        $update = ['last_message_at' => Db::now(), 'updated_at' => Db::now()];
        if ((string) $conversation['title'] === '' || (string) $conversation['title'] === t('New session')) {
            $update['title'] = mb_substr(Markdown::plain($question, 70), 0, 160) ?: t('New session');
        }
        Db::update('ai_conversations', $update, ['id' => (int) $conversation['id']]);
    }

    /** The mode an answer was written in: its own, or its conversation's for answers saved before messages kept one. */
    public static function answerMode(array $message, array $conversation): string
    {
        return (string) ($message['mode'] ?? $conversation['mode']);
    }

    /**
     * Messages with their citations (and file types for slide/page labels).
     * @return list<array<string,mixed>>
     */
    public static function loadMessages(int $conversationId, ?int $onlyId = null): array
    {
        $params = ['c' => $conversationId];
        $sql = 'SELECT * FROM ai_messages WHERE conversation_id = :c';
        if ($onlyId !== null) {
            $sql .= ' AND id = :id';
            $params['id'] = $onlyId;
        }
        $messages = Db::all($sql . ' ORDER BY id', $params);
        if (!$messages) {
            return [];
        }
        [$in, $inParams] = Db::in(array_map(static fn ($m) => (int) $m['id'], $messages), 'm');
        $citations = Db::all(
            "SELECT ac.*, mv.extension FROM ai_citations ac LEFT JOIN material_versions mv ON mv.id = ac.material_version_id
             WHERE ac.message_id IN $in ORDER BY ac.message_id, ac.ref_no",
            $inParams
        );
        $byMessage = [];
        foreach ($citations as $c) {
            $byMessage[(int) $c['message_id']][] = $c;
        }
        foreach ($messages as &$m) {
            $m['citations'] = $byMessage[(int) $m['id']] ?? [];
            [$m['display'], $m['plan']] = self::extractPlan((string) $m['content']);
        }
        return $messages;
    }

    /**
     * Splits a ```plan block out of a study-plan answer.
     * @return array{0:string,1:list<array{starts_at:string,minutes:int,title:string}>}
     */
    public static function extractPlan(string $text): array
    {
        $sessions = [];
        if (!preg_match('/```plan\s*\n(.*?)\n```/s', $text, $m)) {
            return [$text, []];
        }
        foreach (explode("\n", $m[1]) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 3) {
                continue;
            }
            [$when, $minutes, $task] = $parts;
            $utc = Dates::toUtc(str_replace(' ', 'T', $when));
            if ($utc === null || !ctype_digit($minutes) || $task === '') {
                continue;
            }
            $sessions[] = ['starts_at' => $utc, 'minutes' => max(5, min(240, (int) $minutes)), 'title' => mb_substr($task, 0, 200)];
            if (count($sessions) >= 12) {
                break;
            }
        }
        return [trim(str_replace($m[0], '', $text)), $sessions];
    }
}

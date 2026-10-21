<?php

declare(strict_types=1);

namespace Sibol\Services\Ai\Evaluation;

use Sibol\Core\Audit;
use Sibol\Core\Auth;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\RateLimiter;
use Sibol\Core\Translator;
use Sibol\Policies\Access;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\AiProvider;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Settings;

/**
 * The AI check (bin/sibol.php ai:eval). Each case goes through the real companion turn
 * (limits, permission-filtered retrieval, prompt, provider, citation resolution, storage)
 * as a member of the fictional demo school, and the stored answer is checked.
 *
 * Everything a case creates (conversation, messages, citations, usage events) is deleted
 * straight after it, the person's burst counter is put back as it was, and policies are
 * changed in memory only, so a run leaves no conversation text behind and does not use up
 * the demo accounts' limits. The run is audit-logged with counts and token totals only.
 */
final class Runner
{
    private ?AiProvider $provider;
    private RecordingProvider $recorder;
    /** @var array<string,mixed> */
    private array $org = [];
    /** @var array<string,int> class name => id */
    private array $classes = [];
    /** @var array<string,int> next member to use per class and role */
    private array $turns = [];

    /** @param AiProvider|null $provider the provider to check; null means the one configured in .env */
    public function __construct(?AiProvider $provider = null)
    {
        $this->provider = $provider;
    }

    /** @return array<string,mixed> */
    public static function demoSchool(): array
    {
        $org = Db::one('SELECT * FROM organizations WHERE is_demo = 1 ORDER BY id LIMIT 1');
        if (!$org) {
            throw new \RuntimeException('The AI check runs on the fictional demo school, and this database has none. Run it on a local copy after "php bin/sibol.php db:reset" (which deletes data), with the same AI settings in .env.');
        }
        return $org;
    }

    /**
     * @param list<string> $only case ids to run; all when empty
     * @param callable(array<string,mixed>):void|null $progress receives each result as it finishes
     * @return array{provider:string,model:string,demo:bool,results:list<array<string,mixed>>,passed:int,failed:int,input:int,output:int,cost:float}
     */
    public function run(array $only = [], ?callable $progress = null): array
    {
        $this->org = self::demoSchool();
        $this->classes = self::demoClasses((int) $this->org['id']);
        $cases = array_values(array_filter(Cases::all(), static fn (array $c): bool => !$only || in_array($c['id'], $only, true)));
        if (!$cases) {
            throw new \RuntimeException('No case has the id ' . implode(', ', $only) . '.');
        }
        try {
            $inner = $this->provider ?? Providers::make();
        } catch (AiException $e) {
            throw new \RuntimeException($e->getMessage());
        }
        $this->recorder = new RecordingProvider($inner);
        $this->turns = [];
        // A run stopped part-way (Ctrl+C) cannot clean up after itself; its conversation goes now.
        Db::exec("DELETE FROM ai_conversations WHERE organization_id = :o AND title LIKE 'AI check ___'", ['o' => (int) $this->org['id']]);

        $locale = Translator::locale();
        $results = [];
        try {
            Translator::setLocale('en');
            Dates::setTimezone((string) $this->org['timezone']);
            foreach ($cases as $case) {
                $result = $this->runCase($case);
                $results[] = $result;
                if ($progress !== null) {
                    $progress($result);
                }
            }
        } finally {
            Providers::fake(null);
            Settings::flush();
            Auth::actingAs(null);
            Dates::setTimezone(null);
            Translator::setLocale($locale);
        }

        $passed = count(array_filter($results, static fn (array $r): bool => $r['passed']));
        $summary = [
            'provider' => $inner->name(),
            'model' => $inner->model(),
            'demo' => $inner->isDemo(),
            'results' => $results,
            'passed' => $passed,
            'failed' => count($results) - $passed,
            'input' => (int) array_sum(array_column($results, 'input')),
            'output' => (int) array_sum(array_column($results, 'output')),
            'cost' => round((float) array_sum(array_column($results, 'cost')), 6),
        ];
        Audit::log('ai.eval_run', null, null, [
            'organization_id' => (int) $this->org['id'],
            'provider' => $summary['provider'],
            'model' => $summary['model'],
            'cases' => count($results),
            'passed' => $summary['passed'],
            'failed' => $summary['failed'],
            'input' => $summary['input'],
            'output' => $summary['output'],
            'cost_usd' => round($summary['cost'], 4),
        ]);
        return $summary;
    }

    /** @return array<string,mixed> */
    private function runCase(array $case): array
    {
        $started = microtime(true);
        $person = $this->person($case);
        $this->recorder->reset();
        Providers::fake($this->recorder);
        $this->standardPolicy();
        Auth::actingAs($person);
        $usageMark = (int) Db::value('SELECT COALESCE(MAX(id), 0) FROM ai_usage_events');
        $burst = RateLimiter::windows('ai-burst', (string) $person['id']);
        $conversationId = 0;
        $failures = [];
        $answer = '';
        try {
            $conversationId = $this->startConversation($person, $case);
            $conversation = (array) Db::one(
                'SELECT c.*, cl.name AS class_name, m.title AS material_title FROM ai_conversations c
                 LEFT JOIN classes cl ON cl.id = c.class_id LEFT JOIN learning_materials m ON m.id = c.material_id WHERE c.id = :id',
                ['id' => $conversationId]
            );
            switch ($case['kind'] ?? 'answer') {
                case 'refusal':
                    [$failures, $answer] = $this->refusalCase($person, $conversation, $case, $usageMark);
                    break;
                case 'limit':
                    [$failures, $answer] = $this->limitCase($person, $conversation, $case, $usageMark);
                    break;
                default:
                    [$failures, $answer] = $this->answerCase($person, $conversation, $case);
            }
        } catch (\Throwable $e) {
            $failures[] = 'the check could not finish: ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 160);
        } finally {
            if ($conversationId > 0) {
                // Messages and citations go with the conversation.
                Db::exec('DELETE FROM ai_conversations WHERE id = :id', ['id' => $conversationId]);
            }
            Db::exec('DELETE FROM ai_usage_events WHERE id > :m AND user_id = :u', ['m' => $usageMark, 'u' => (int) $person['id']]);
            RateLimiter::restore('ai-burst', (string) $person['id'], $burst);
            Settings::flush();
            Auth::actingAs(null);
        }

        $in = $this->recorder->inputTokens;
        $out = $this->recorder->outputTokens;
        return [
            'id' => $case['id'],
            'group' => $case['group'],
            'title' => $case['title'],
            'passed' => $failures === [],
            'failures' => $failures,
            'input' => $in,
            'output' => $out,
            'cost' => Usage::estimate($this->recorder->servedModel !== '' ? $this->recorder->servedModel : $this->recorder->model(), $in, $out),
            'seconds' => round(microtime(true) - $started, 1),
            'answer' => $answer,
        ];
    }

    /** @return array{0:list<string>,1:string} failures and the stored answer */
    private function answerCase(array $person, array $conversation, array $case): array
    {
        $question = (string) $case['message'];
        $sources = Companion::gatherSources($person, $conversation, $question);
        $raw = '';
        $errors = [];
        Companion::turn($person, $conversation, $question, static function (array $event) use (&$raw, &$errors): void {
            if ($event['type'] === 'delta') {
                $raw .= (string) $event['text'];
            } elseif ($event['type'] === 'error') {
                $errors[] = (string) ($event['code'] ?? 'error');
            }
        }, static fn (): bool => false);

        $message = Db::one("SELECT * FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => (int) $conversation['id']]);
        if ($errors || !$message || $message['status'] !== 'complete') {
            $why = $errors ? 'the turn ended with the error "' . implode(', ', $errors) . '"' : 'the answer was ' . ($message['status'] ?? 'not stored');
            return [['no answer: ' . $why], (string) ($message['content'] ?? '')];
        }
        $content = (string) $message['content'];
        if ($message['error_code'] === 'refused') {
            return [['the provider refused to answer'], $content];
        }
        $citations = Db::all(
            'SELECT c.ref_no, c.chunk_id, m.title AS material_title, ch.section_title, ch.content
             FROM ai_citations c LEFT JOIN learning_materials m ON m.id = c.material_id LEFT JOIN material_chunks ch ON ch.id = c.chunk_id
             WHERE c.message_id = :m ORDER BY c.ref_no',
            ['m' => (int) $message['id']]
        );

        $failures = [];
        $demo = $this->recorder->isDemo();
        if ($demo && ((int) $message['is_demo'] !== 1 || !self::hasDemoLabel($content))) {
            $failures[] = 'the demo answer is not labelled as demo mode';
        } elseif (!$demo && (int) $message['is_demo'] === 1) {
            $failures[] = 'a model answer is labelled as demo mode';
        }
        if ($this->recorder->truncated) {
            $failures[] = 'the answer was cut short at the token limit';
        }

        // The passages handed over, and what the answer says about them.
        $numbers = array_map(static fn (array $s): int => (int) $s['n'], $sources);
        if ($this->recorder->sourceNumbers !== null && $this->recorder->sourceNumbers !== $numbers) {
            $failures[] = 'the model was given different passages from the ones the check retrieved';
        }
        foreach ($this->outOfScope($person, $sources) as $n) {
            $failures[] = "passage [$n] is outside the person's classes or not released to them";
        }
        $invented = array_values(array_unique(array_diff(Checks::markers($raw), $numbers)));
        if ($invented) {
            $failures[] = 'cites ' . self::refs($invented) . ($numbers ? ', but only ' . self::refs($numbers) . ' were supplied' : ' when no passages were supplied');
        }
        $byNumber = [];
        foreach ($sources as $s) {
            $byNumber[(int) $s['n']] = $s;
        }
        foreach ($citations as $c) {
            if ((int) ($byNumber[(int) $c['ref_no']]['chunk_id'] ?? 0) !== (int) $c['chunk_id']) {
                $failures[] = 'the stored citation [' . $c['ref_no'] . '] does not point to the passage supplied under that number';
            }
        }
        $quotes = Checks::unsupportedQuotes($raw, array_merge(array_map(static fn (array $s): string => (string) $s['content'], $sources), [$question]));
        if ($quotes) {
            $failures[] = count($quotes) === 1 ? 'a quotation is not in the supplied passages' : count($quotes) . ' quotations are not in the supplied passages';
        }
        $pages = [];
        foreach ($sources as $s) {
            if ($s['page_from'] !== null) {
                $pages = array_merge($pages, range((int) $s['page_from'], (int) ($s['page_to'] ?? $s['page_from'])));
            }
        }
        $badPages = array_values(array_diff(Checks::pages($raw), $pages));
        if ($badPages) {
            $failures[] = 'mentions page ' . implode(', ', $badPages) . ', which no supplied passage covers';
        }

        $expect = (array) $case['expect'];
        if (isset($expect['cites'])) {
            [$title, $word] = $expect['cites'];
            $hit = false;
            foreach ($citations as $c) {
                if ((string) $c['material_title'] === $title && preg_match('/(?<!\p{L})' . preg_quote($word, '/') . '/iu', $c['section_title'] . ' ' . $c['content'])) {
                    $hit = true;
                }
            }
            if (!$hit) {
                $failures[] = "no citation of the \"$title\" passage about \"$word\"";
            }
        }
        if (isset($expect['citations']) && count($citations) < (int) $expect['citations']) {
            $failures[] = sprintf('cites %d passage(s); at least %d expected', count($citations), (int) $expect['citations']);
        }
        if (($expect['covered'] ?? true) === false && !Checks::saysNotCovered($content)) {
            $failures[] = sprintf('does not say within %d characters that the class materials do not cover this', Checks::NOT_COVERED_WITHIN);
        }
        if (!empty($expect['greeting'])) {
            if (Checks::saysNotCovered($content)) {
                $failures[] = 'says the class materials do not cover a greeting';
            }
            if (!Checks::asksBack($content)) {
                $failures[] = 'does not ask what they would like to work on';
            }
        }
        if (isset($expect['graded'])) {
            foreach ((array) ($expect['graded']['forbid'] ?? []) as $pattern) {
                if (preg_match($pattern, $content)) {
                    $failures[] = 'gives the graded answer itself';
                    break;
                }
            }
            [$longest, $total] = Checks::proseWords($content);
            if ($longest > Checks::GRADED_PARAGRAPH_WORDS || $total > Checks::GRADED_TOTAL_WORDS) {
                $failures[] = sprintf('writes %d words of finished prose (longest paragraph %d)', $total, $longest);
            }
            if (!Checks::offersHelp($content)) {
                $failures[] = 'does not offer a way to do the work themselves';
            }
        }
        if (($expect['language'] ?? null) === 'fil') {
            [$ok, $share] = Checks::inFilipino($content);
            if (!$ok) {
                $failures[] = sprintf('not in Filipino (%d%% of its function words are Filipino)', (int) round($share * 100));
            }
        }
        if (!empty($expect['asks']) && !Checks::asksBack($content)) {
            $failures[] = 'does not ask the student anything';
        }
        if (isset($expect['stays']) && !Checks::staysOnTask($content, (string) $expect['stays']['about'], (array) ($expect['stays']['forbid'] ?? []))) {
            $failures[] = 'answers a message this kind of help is not for instead of steering back to it';
        }
        if (!empty($expect['plan']) && !Checks::hasPlan($content)) {
            $failures[] = 'has no ```plan block of dated sessions for the calendar';
        }
        return [$failures, $content];
    }

    /**
     * A refusal cannot be triggered safely on purpose, so a scripted provider streams a few
     * words and then refuses, the way Claude reports stop_reason "refusal".
     * @return array{0:list<string>,1:string}
     */
    private function refusalCase(array $person, array $conversation, array $case, int $usageMark): array
    {
        $partial = 'Here is the first part of an answer that';
        Providers::fake(new RecordingProvider(ScriptedProvider::refusing($partial)));
        $done = null;
        try {
            Companion::turn($person, $conversation, (string) $case['message'], static function (array $event) use (&$done): void {
                if ($event['type'] === 'done') {
                    $done = $event;
                }
            }, static fn (): bool => false);
        } finally {
            Providers::fake($this->recorder);
        }
        $message = Db::one("SELECT * FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => (int) $conversation['id']]);
        $failures = [];
        $safe = t('I can\'t help with that request. If you think this is a mistake, try asking in a different way, or ask your teacher.');
        if (!$message || (string) $message['content'] !== $safe) {
            $failures[] = 'the stored answer is not the safe refusal message';
        }
        if ($message && (string) $message['error_code'] !== 'refused') {
            $failures[] = 'the answer is not recorded as refused';
        }
        if ($done === null) {
            $failures[] = 'the turn did not finish';
        } elseif (str_contains((string) ($done['html'] ?? ''), $partial)) {
            $failures[] = 'the refused partial text is still shown';
        }
        if ($message && Db::value('SELECT COUNT(*) FROM ai_citations WHERE message_id = :m', ['m' => (int) $message['id']])) {
            $failures[] = 'the refusal carries citations';
        }
        if (!Db::value("SELECT COUNT(*) FROM ai_usage_events WHERE id > :m AND user_id = :u AND error_code = 'refused'", ['m' => $usageMark, 'u' => (int) $person['id']])) {
            $failures[] = 'the refusal is not counted in AI usage';
        }
        return [$failures, (string) ($message['content'] ?? '')];
    }

    /** @return array{0:list<string>,1:string} */
    private function limitCase(array $person, array $conversation, array $case, int $usageMark): array
    {
        $orgId = (int) $this->org['id'];
        $code = (string) $case['limit'];
        switch ($code) {
            case 'daily_limit':
                // One counted request today, and a daily limit of that many.
                Db::insert('ai_usage_events', [
                    'organization_id' => $orgId, 'user_id' => (int) $person['id'], 'user_role' => (string) $person['role'], 'feature' => 'companion',
                    'provider' => $this->recorder->name(), 'model' => $this->recorder->model(), 'is_demo' => $this->recorder->isDemo() ? 1 : 0,
                    'status' => 'ok', 'input_tokens' => 0, 'output_tokens' => 0, 'created_at' => Db::now(),
                ]);
                $limit = Usage::usedToday($person);
                $student = $person['role'] === 'student';
                Settings::override($orgId, $student ? 'ai.student_daily_messages' : 'ai.teacher_daily_requests', $limit);
                $expected = $student
                    ? t('You have used today\'s :n companion messages. The limit resets at midnight.', ['n' => $limit])
                    : t('You have used today\'s :n AI requests. The limit resets at midnight.', ['n' => $limit]);
                break;
            case 'burst':
                // A fixed one-minute window: do not start in its last seconds.
                $left = 60 - time() % 60;
                if ($left <= 3) {
                    sleep($left + 1);
                }
                for ($i = 0; $i < 8; $i++) {
                    RateLimiter::attempt('ai-burst', (string) $person['id'], 8, 60);
                }
                $expected = t('That is a lot of requests in one minute. Wait a moment, then try again.');
                break;
            default:
                Settings::override($orgId, 'ai.enabled', false);
                $expected = t('AI tools are turned off at your school.');
        }

        $error = null;
        Companion::turn($person, $conversation, (string) $case['message'], static function (array $event) use (&$error): void {
            if ($event['type'] === 'error' && $error === null) {
                $error = $event;
            }
        }, static fn (): bool => false);

        $failures = [];
        if ($error === null || ($error['code'] ?? '') !== 'limit') {
            $failures[] = 'the request was not stopped';
        } elseif ((string) $error['message'] !== $expected) {
            $failures[] = 'the person is not told which limit applies';
        }
        if ($this->recorder->calls > 0) {
            $failures[] = 'the request reached the AI provider';
        }
        if (Db::value('SELECT COUNT(*) FROM ai_messages WHERE conversation_id = :c', ['c' => (int) $conversation['id']])) {
            $failures[] = 'messages were stored for a stopped request';
        }
        $counted = (int) Db::value(
            "SELECT COUNT(*) FROM ai_usage_events WHERE id > :m AND user_id = :u AND error_code = :code AND status IN ('blocked', 'rate_limited')",
            ['m' => $usageMark, 'u' => (int) $person['id'], 'code' => $code]
        );
        if ($counted !== 1) {
            $failures[] = 'the stopped request is not counted for the usage report';
        }
        return [$failures, (string) ($error['message'] ?? '')];
    }

    /** The policy every case starts from, whatever the demo school's administrator has changed. In memory only. */
    private function standardPolicy(): void
    {
        Settings::flush();
        $orgId = (int) $this->org['id'];
        foreach ([
            'ai.enabled' => true, 'ai.teacher_tools' => true, 'ai.general_answers' => true, 'features.document_answers' => true,
            'ai.student_daily_messages' => 0, 'ai.teacher_daily_requests' => 0, 'ai.monthly_token_budget' => 0,
        ] as $key => $value) {
            Settings::override($orgId, $key, $value);
        }
    }

    /** An active member of the case's class, taking students in turn so no one hits the burst limit. */
    private function person(array $case): array
    {
        $classId = $this->classes[$case['class']];
        $ids = array_map('intval', Db::column(
            "SELECT u.id FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             WHERE cm.class_id = :c AND cm.role = :r AND cm.status = 'active' AND u.status = 'active' ORDER BY u.id",
            ['c' => $classId, 'r' => (string) $case['role']]
        ));
        if (!$ids) {
            throw new \RuntimeException('The demo class "' . $case['class'] . '" has no active ' . $case['role'] . '. Reset the demo school and run the check again.');
        }
        $key = $classId . ':' . $case['role'];
        $index = $this->turns[$key] ?? 0;
        $this->turns[$key] = $index + 1;
        return (array) Db::one(
            'SELECT u.*, o.name AS org_name, o.timezone AS org_timezone FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.id = :id',
            ['id' => $ids[$index % count($ids)]]
        );
    }

    private function startConversation(array $person, array $case): int
    {
        $classId = $this->classes[$case['class']];
        $materialId = null;
        if (!empty($case['material'])) {
            $materialId = (int) Db::value('SELECT id FROM learning_materials WHERE class_id = :c AND title = :t ORDER BY id LIMIT 1', ['c' => $classId, 't' => $case['material']]) ?: null;
        }
        return Db::insert('ai_conversations', [
            'organization_id' => (int) $person['organization_id'],
            'user_id' => (int) $person['id'],
            'class_id' => empty($case['all_classes']) ? $classId : null,
            'material_id' => $materialId,
            'mode' => (string) $case['mode'],
            'title' => 'AI check ' . $case['id'],
            'language' => (string) $case['language'],
            'last_message_at' => Db::now(),
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
    }

    /** @return list<int> numbers of passages the person may not see */
    private function outOfScope(array $person, array $sources): array
    {
        $student = $person['role'] === 'student';
        $classIds = $student ? Access::studentClassIds($person) : Access::teacherClassIds($person, true);
        $visible = $student ? Access::releasedMaterialSql('m') : "m.visibility <> 'archived'";
        $bad = [];
        foreach ($sources as $s) {
            $ok = in_array((int) $s['class_id'], $classIds, true) && Db::value(
                "SELECT 1 FROM material_chunks c JOIN learning_materials m ON m.id = c.material_id AND m.current_version_id = c.material_version_id
                 WHERE c.id = :id AND m.ai_enabled = 1 AND $visible",
                ['id' => (int) $s['chunk_id']]
            );
            if (!$ok) {
                $bad[] = (int) $s['n'];
            }
        }
        return $bad;
    }

    /**
     * The demo classes and materials the cases are written against.
     * @return array<string,int> class name => id
     */
    private static function demoClasses(int $orgId): array
    {
        $classes = [];
        $missing = [];
        foreach ([Cases::SCIENCE, Cases::SCIENCE_MOLAVE, Cases::MATH, Cases::ENGLISH] as $name) {
            $id = (int) Db::value("SELECT id FROM classes WHERE organization_id = :o AND name = :n AND status = 'active' ORDER BY id LIMIT 1", ['o' => $orgId, 'n' => $name]);
            if ($id > 0) {
                $classes[$name] = $id;
            } else {
                $missing[] = 'class "' . $name . '"';
            }
        }
        foreach ([[Cases::SCIENCE, Cases::BREATHING], [Cases::SCIENCE, Cases::HEREDITY], [Cases::MATH, Cases::QUADRATICS], [Cases::ENGLISH, Cases::INFERENCES]] as [$class, $title]) {
            $ready = isset($classes[$class]) && Db::value(
                "SELECT 1 FROM learning_materials m JOIN material_versions v ON v.id = m.current_version_id AND v.processing_status = 'ready'
                 WHERE m.class_id = :c AND m.title = :t AND m.ai_enabled = 1 AND " . Access::releasedMaterialSql('m'),
                ['c' => $classes[$class], 't' => $title]
            );
            if (!$ready) {
                $missing[] = 'material "' . $title . '"';
            }
        }
        if ($missing) {
            throw new \RuntimeException('The demo school has changed (missing ' . implode(', ', $missing) . '). Reset it with "php bin/sibol.php db:reset" on a local copy, then run the check again.');
        }
        return $classes;
    }

    private static function hasDemoLabel(string $content): bool
    {
        foreach (Checks::demoLabels() as $label) {
            if (str_starts_with(ltrim($content), '**' . $label . ':**')) {
                return true;
            }
        }
        return false;
    }

    /** "[1], [3]" for messages. @param list<int> $numbers */
    private static function refs(array $numbers): string
    {
        sort($numbers);
        return implode(', ', array_map(static fn (int $n): string => '[' . $n . ']', $numbers));
    }
}

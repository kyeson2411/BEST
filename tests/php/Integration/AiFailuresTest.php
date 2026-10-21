<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Http;
use Sibol\Services\Ai\Usage;

/**
 * When the AI service is down, slow or busy, the student hears it in plain words, waits for one timeout at most,
 * and keeps their messages for the day.
 */
final class AiFailuresTest extends IntegrationTestCase
{
    public function testAFailedRequestDoesNotUseUpADailyMessage(): void
    {
        $andrea = $this->person('andrea.cruz');
        $used = Usage::usedToday($andrea);
        $left = Usage::remainingToday($andrea);
        $ids = [];
        foreach (['error', 'ok', 'cancelled'] as $status) {
            $ids[] = Db::insert('ai_usage_events', [
                'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'], 'user_role' => 'student',
                'feature' => 'companion', 'provider' => 'openai_compatible', 'model' => 'test-model', 'is_demo' => 0, 'status' => $status,
                'input_tokens' => 0, 'output_tokens' => 0, 'error_code' => $status === 'error' ? 'timeout' : null, 'created_at' => Db::now(),
            ]);
        }
        try {
            self::assertSame($used + 2, Usage::usedToday($andrea), 'An answer and a stopped answer count; a failure does not');
            self::assertSame($left - 2, Usage::remainingToday($andrea));
        } finally {
            [$in, $params] = Db::in($ids, 'id');
            Db::exec("DELETE FROM ai_usage_events WHERE id IN $in", $params);
        }
    }

    public function testASilentServiceIsWaitedForOnceAndNamedPlainly(): void
    {
        // A server that accepts the connection and never answers.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, $errstr);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        $started = microtime(true);
        try {
            Http::postJson('http://127.0.0.1:' . $port . '/v1/chat/completions', [], ['model' => 'm'], 1);
            self::fail('A silent service must time out');
        } catch (AiException $e) {
            self::assertSame('timeout', $e->errorCode);
            self::assertFalse($e->retryable, 'The provider does not wait for it twice more');
            self::assertSame('The AI service is taking too long to answer. Try again in a few minutes. Everything else in Sibol works as usual.', $e->getMessage());
        } finally {
            fclose($server);
        }
        self::assertLessThan(5, microtime(true) - $started);
    }

    public function testAServiceThatCannotBeReachedIsTriedAgainAndNamedPlainly(): void
    {
        // A port nobody listens on any more: the connection is refused.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, $errstr);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        fclose($server);
        try {
            Http::postJson('http://127.0.0.1:' . $port . '/v1/chat/completions', [], ['model' => 'm'], 5);
            self::fail('A closed port must fail');
        } catch (AiException $e) {
            self::assertSame('network', $e->errorCode);
            self::assertTrue($e->retryable);
            self::assertSame('Sibol can\'t reach the AI service right now. Try again in a few minutes. Everything else in Sibol works as usual.', $e->getMessage());
        }
    }

    public function testProviderErrorsSayWhatToDoWithoutSettingNames(): void
    {
        foreach ([401, 403, 404] as $status) {
            $e = AiException::fromStatus($status, 'Invalid API Key');
            self::assertStringStartsWith('The school\'s AI service is not set up correctly right now. Tell your teacher or the school administrator.', $e->getMessage());
            self::assertDoesNotMatchRegularExpression('/API key|AI_MODEL|AI_API_KEY/i', $e->getMessage());
            self::assertFalse($e->retryable);
        }
        self::assertSame('auth', AiException::fromStatus(401, '')->errorCode);
        self::assertSame('model_unavailable', AiException::fromStatus(404, '')->errorCode);

        // Groq's free plan answers 413 when a request does not fit its tokens-per-minute limit: that is "busy".
        $busy = AiException::fromStatus(413, 'Request too large for model `openai/gpt-oss-120b` in organization `org_x` service tier `on_demand` on tokens per minute (TPM): Limit 8000, Requested 8779, please reduce your message size and try again.');
        self::assertSame('rate_limited', $busy->errorCode);
        self::assertTrue($busy->retryable);
        self::assertSame('The AI service is busy right now. Try again in a minute.', $busy->getMessage());
        self::assertSame('too_large', AiException::fromStatus(413, 'Payload too large')->errorCode);
    }
}

<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\AttemptContextInterface;
use Gohany\Retry\GiveUpReason;
use Gohany\Retry\RetryerInterface;
use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\RetryPolicyInterface;
use Gohany\Retry\SleeperInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

/**
 * SPEC §5.1 and §5.2, end to end: parse a spec, run an operation, record the actual sleeps.
 *
 * The retryer must take its clock, sleeper and Randomizer by injection, and must not spend
 * any time outside the sleeper (operations here take no time on the FrozenClock).
 */
abstract class RetryerContractTestCase extends TestCase
{
    protected const SEED = 4242;

    abstract protected function createRetryer(SleeperInterface $sleeper, ClockInterface $clock, Randomizer $random): RetryerInterface;

    abstract protected function createPolicyFactory(): RetryPolicyFactoryInterface;

    private RecordingSleeper $sleeper;
    private RetryerInterface $retryer;

    protected function setUp(): void
    {
        $clock = new FrozenClock();
        $this->sleeper = new RecordingSleeper($clock);
        $this->retryer = $this->createRetryer($this->sleeper, $clock, SeededRandom::randomizer(self::SEED));
    }

    private function alwaysRetry(string $spec): RetryPolicyInterface
    {
        return $this->createPolicyFactory()->fromSpec($spec, FixedDecider::always());
    }

    /**
     * An operation that fails $failures times, then returns 'ok'. Each failure is a new
     * exception whose message names its attempt.
     */
    private static function failingTimes(int $failures, ?array &$seen = null): \Closure
    {
        $seen = [];
        return static function (AttemptContextInterface $attempt) use ($failures, &$seen): string {
            $seen[] = $attempt->attemptNumber();
            if ($attempt->attemptNumber() <= $failures) {
                throw new \RuntimeException('attempt ' . $attempt->attemptNumber());
            }
            return 'ok';
        };
    }

    public function testReturnsTheValueWithoutSleeping(): void
    {
        self::assertSame('ok', $this->retryer->try(self::failingTimes(0), $this->alwaysRetry('rtry:a=3;d=100')));
        self::assertSame([], $this->sleeper->sleeps());
    }

    public function testSpecToSleeps(): void
    {
        $policy = $this->alwaysRetry('rtry:a=4;d=100;m=exp;b=2;j=100@full');

        $value = $this->retryer->try(self::failingTimes(3, $seen), $policy);

        self::assertSame('ok', $value);
        self::assertSame([1, 2, 3, 4], $seen);
        self::assertSame(SeededRandom::expectedDraws(self::SEED, [[0, 100], [100, 200], [300, 400]]), $this->sleeper->sleeps());
    }

    public function testStartAfterSleepsBeforeTheFirstAttempt(): void
    {
        $this->retryer->try(self::failingTimes(1), $this->alwaysRetry('rtry:a=2;d=100;j=0;sa=50'));
        self::assertSame([50, 100], $this->sleeper->sleeps());
    }

    public function testTryOutcomeReportsSuccess(): void
    {
        $result = $this->retryer->tryOutcome(self::failingTimes(1), $this->alwaysRetry('rtry:a=3;d=10;j=0'));
        self::assertTrue($result->isSuccess());
        self::assertSame('ok', $result->value());
        self::assertNull($result->lastError());
        self::assertSame(2, $result->attempts());
        self::assertNull($result->giveUpReason());
    }

    public function testTryRethrowsTheLastErrorThatTryOutcomeReports(): void
    {
        $policy = $this->alwaysRetry('rtry:a=4;d=10;j=0');

        $result = $this->retryer->tryOutcome(self::failingTimes(99), $policy);
        self::assertFalse($result->isSuccess());
        self::assertNull($result->value());
        self::assertSame(4, $result->attempts());
        self::assertSame(GiveUpReason::AttemptsExhausted, $result->giveUpReason());
        self::assertInstanceOf(\RuntimeException::class, $result->lastError());
        self::assertSame('attempt 4', $result->lastError()->getMessage());

        try {
            $this->retryer->try(self::failingTimes(99), $policy);
            self::fail('try() must rethrow on give-up');
        } catch (\RuntimeException $e) {
            self::assertSame('attempt 4', $e->getMessage());
        }
    }

    public function testTryRethrowsTheSameInstance(): void
    {
        $error = new \LogicException('the one');
        $thrown = null;
        try {
            $this->retryer->try(static fn () => throw $error, $this->alwaysRetry('rtry:a=2;d=1;j=0'));
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        self::assertSame($error, $thrown);

        $result = $this->retryer->tryOutcome(static fn () => throw $error, $this->alwaysRetry('rtry:a=2;d=1;j=0'));
        self::assertSame($error, $result->lastError());
    }

    public function testNotRetryableStopsAfterOneAttempt(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=5;d=100', FixedDecider::never());
        $result = $this->retryer->tryOutcome(self::failingTimes(99), $policy);

        self::assertSame(1, $result->attempts());
        self::assertSame(GiveUpReason::NotRetryable, $result->giveUpReason());
        self::assertSame([], $this->sleeper->sleeps());
    }

    public function testStopsAtTheDeadlineWithoutSleepingPastIt(): void
    {
        // Retry 1 waits 100 ms (budget 250 left); retry 2 would wait 200 ms with 150 left.
        $result = $this->retryer->tryOutcome(self::failingTimes(99), $this->alwaysRetry('rtry:a=10;d=100;j=0;dl=250'));

        self::assertSame(GiveUpReason::DeadlineExceeded, $result->giveUpReason());
        self::assertSame(2, $result->attempts());
        self::assertSame([100], $this->sleeper->sleeps());
    }

    public function testPassesContextAndAttemptNumbers(): void
    {
        $seen = [];
        $this->retryer->try(
            static function (AttemptContextInterface $attempt) use (&$seen): string {
                $seen[] = [$attempt->attemptNumber(), $attempt->maxAttempts(), $attempt->context()['tenant'] ?? null];
                if ($attempt->attemptNumber() < 3) {
                    throw new \RuntimeException('again');
                }
                return 'ok';
            },
            $this->alwaysRetry('rtry:a=3;d=1;j=0'),
            ['tenant' => 't_1']
        );
        self::assertSame([[1, 3, 't_1'], [2, 3, 't_1'], [3, 3, 't_1']], $seen);
    }
}

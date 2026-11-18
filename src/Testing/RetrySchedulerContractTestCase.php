<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\GiveUpReason;
use Gohany\Retry\RetryDecision;
use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\RetryPolicyInterface;
use Gohany\Retry\RetrySchedulerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

/**
 * SPEC §5.11: the pure scheduling call.
 */
abstract class RetrySchedulerContractTestCase extends TestCase
{
    protected const SEED = 20261117;

    abstract protected function createScheduler(ClockInterface $clock, Randomizer $random): RetrySchedulerInterface;

    abstract protected function createPolicyFactory(): RetryPolicyFactoryInterface;

    private FrozenClock $clock;
    private RetrySchedulerInterface $scheduler;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->scheduler = $this->createScheduler($this->clock, SeededRandom::randomizer(self::SEED));
    }

    private function policy(string $spec): RetryPolicyInterface
    {
        return $this->createPolicyFactory()->fromSpec($spec);
    }

    private function next(RetryPolicyInterface $policy, int $failedAttempt, ?TestOutcome $outcome = null, ?int $remainingBudgetMs = null): RetryDecision
    {
        return $this->scheduler->next(
            $policy,
            $outcome ?? TestOutcome::failure(503),
            new TestAttemptContext($failedAttempt, $policy->attempts(), $remainingBudgetMs)
        );
    }

    public function testSeededExponentialScheduleAndGiveUpAtTheLastAttempt(): void
    {
        $policy = $this->policy('rtry:a=4;d=100;m=exp;b=2;j=100@full');

        $delays = [];
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $decision = $this->next($policy, $attempt);
            self::assertTrue($decision->retry(), "attempt {$attempt} should retry");
            self::assertNull($decision->giveUpReason());
            $delays[] = $decision->delayMs();
        }

        // nominal 100, 200, 400; full jitter with a 100 ms window
        self::assertSame(SeededRandom::expectedDraws(self::SEED, [[0, 100], [100, 200], [300, 400]]), $delays);

        $last = $this->next($policy, 4);
        self::assertFalse($last->retry());
        self::assertSame(GiveUpReason::AttemptsExhausted, $last->giveUpReason());
        self::assertSame(0, $last->delayMs());
    }

    public function testAbsentJitterMeansFullJitter(): void
    {
        $policy = $this->policy('rtry:a=3;d=1000');
        $delays = [$this->next($policy, 1)->delayMs(), $this->next($policy, 2)->delayMs()];
        self::assertSame(SeededRandom::expectedDraws(self::SEED, [[0, 1000], [0, 2000]]), $delays);
    }

    public function testCapAppliesBeforeJitter(): void
    {
        $policy = $this->policy('rtry:a=6;d=100;b=2;cap=250;j=0');
        $delays = [];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $delays[] = $this->next($policy, $attempt)->delayMs();
        }
        self::assertSame([100, 200, 250, 250, 250], $delays);

        $jittered = $this->policy('rtry:a=6;d=100;b=2;cap=250;j=50@pm');
        self::assertSame(SeededRandom::expectedDraws(self::SEED, [[200, 300]]), [$this->next($jittered, 4)->delayMs()]);
    }

    public function testLinearAndSequenceSchedules(): void
    {
        $lin = $this->policy('rtry:a=4;d=100;m=lin;j=0');
        self::assertSame([100, 200, 300], [$this->next($lin, 1)->delayMs(), $this->next($lin, 2)->delayMs(), $this->next($lin, 3)->delayMs()]);

        $seq = $this->policy('rtry:a=4;m=seq;seq=50,100,1s;j=0');
        self::assertSame([50, 100, 1000], [$this->next($seq, 1)->delayMs(), $this->next($seq, 2)->delayMs(), $this->next($seq, 3)->delayMs()]);
        self::assertSame(GiveUpReason::AttemptsExhausted, $this->next($seq, 4)->giveUpReason());
    }

    public function testHonoursNotBeforeEvenAboveTheCap(): void
    {
        $policy = $this->policy('rtry:a=4;d=100;cap=1s;j=100@full');
        $retryAfter = TestOutcome::failure(429, [], $this->clock->nowUnixMs() + 5000);

        $decision = $this->next($policy, 1, $retryAfter);
        self::assertTrue($decision->retry());
        self::assertSame(5000, $decision->delayMs());
    }

    public function testANotBeforeInThePastLeavesTheJitteredDelay(): void
    {
        $policy = $this->policy('rtry:a=4;d=100;j=100@full');
        $past = TestOutcome::failure(429, [], $this->clock->nowUnixMs() - 5000);
        self::assertSame(SeededRandom::expectedDraws(self::SEED, [[0, 100]]), [$this->next($policy, 1, $past)->delayMs()]);
    }

    public function testIgnoresNotBeforeWhenFollowHeadersIsOff(): void
    {
        $policy = $this->policy('rtry:a=4;d=100;j=0;fh=0');
        $retryAfter = TestOutcome::failure(429, [], $this->clock->nowUnixMs() + 5000);
        self::assertSame(100, $this->next($policy, 1, $retryAfter)->delayMs());
    }

    public function testNonRetryableFailuresGiveUpAtOnce(): void
    {
        $policy = $this->policy('rtry:a=5;d=100');
        foreach ([400, 401, 403, 404, 409, 422] as $status) {
            $decision = $this->next($policy, 1, TestOutcome::failure($status));
            self::assertFalse($decision->retry(), "{$status} must not retry");
            self::assertSame(GiveUpReason::NotRetryable, $decision->giveUpReason());
        }
        self::assertSame(GiveUpReason::NotRetryable, $this->next($policy, 1, TestOutcome::failure())->giveUpReason());
    }

    public function testRetryableFailuresRetry(): void
    {
        $policy = $this->policy('rtry:a=5;d=100');
        foreach ([TestOutcome::failure(408), TestOutcome::failure(429), TestOutcome::failure(500), TestOutcome::failure(503), TestOutcome::failure(null, ['ETIMEDOUT'])] as $outcome) {
            self::assertTrue($this->next($policy, 1, $outcome)->retry());
        }
    }

    public function testNotRetryableWinsOverAttemptsExhausted(): void
    {
        $policy = $this->policy('rtry:a=2;d=100');
        self::assertSame(GiveUpReason::NotRetryable, $this->next($policy, 2, TestOutcome::failure(400))->giveUpReason());
    }

    public function testGivesUpWhenTheDelayReachesTheDeadline(): void
    {
        $policy = $this->policy('rtry:a=5;d=100;j=0;dl=1s');
        self::assertSame(GiveUpReason::DeadlineExceeded, $this->next($policy, 1, null, 100)->giveUpReason());
        self::assertSame(GiveUpReason::DeadlineExceeded, $this->next($policy, 1, null, 0)->giveUpReason());

        $decision = $this->next($policy, 1, null, 101);
        self::assertTrue($decision->retry());
        self::assertSame(100, $decision->delayMs());
    }

    public function testRetryAfterBeyondTheDeadlineGivesUp(): void
    {
        $policy = $this->policy('rtry:a=5;d=100;j=0;dl=1s');
        $retryAfter = TestOutcome::failure(503, [], $this->clock->nowUnixMs() + 2000);
        self::assertSame(GiveUpReason::DeadlineExceeded, $this->next($policy, 1, $retryAfter, 900)->giveUpReason());
    }

    public function testRejectsASuccessfulOutcome(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->next($this->policy('rtry:a=3;d=100'), 1, TestOutcome::success('ok'));
    }
}

<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\AttemptContextInterface;
use Gohany\Retry\AttemptOutcomeInterface;
use Gohany\Retry\GiveUpReason;
use Gohany\Retry\RetryDecision;
use Gohany\Retry\RetryPolicyInterface;
use Gohany\Retry\RetrySchedulerInterface;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

/** SPEC §5.11, step by step. */
final class Scheduler implements RetrySchedulerInterface
{
    public function __construct(private readonly ClockInterface $clock, private readonly Randomizer $random)
    {
    }

    public function next(RetryPolicyInterface $policy, AttemptOutcomeInterface $outcome, AttemptContextInterface $context): RetryDecision
    {
        if ($outcome->isSuccess()) {
            throw new \InvalidArgumentException('next() is for failed attempts');
        }
        if (!$policy->decider()->shouldRetry($outcome, $context)) {
            return RetryDecision::giveUp(GiveUpReason::NotRetryable);
        }
        $n = $context->attemptNumber();
        if ($n >= $policy->attempts()) {
            return RetryDecision::giveUp(GiveUpReason::AttemptsExhausted);
        }
        $delay = $policy->nominalDelayMs($n + 1);
        if ($policy->capMs() !== null) {
            $delay = min($delay, $policy->capMs());
        }
        $delay = $policy->jitter()->apply($delay, $this->random);

        if ($policy->followHeaders() && $outcome->notBeforeUnixMs() !== null) {
            $delay = max($delay, $outcome->notBeforeUnixMs() - self::nowMs($this->clock));
        }
        $remaining = $context->remainingBudgetMs();
        if ($remaining !== null && $delay >= $remaining) {
            return RetryDecision::giveUp(GiveUpReason::DeadlineExceeded);
        }
        return RetryDecision::retryAfter($delay);
    }

    public static function nowMs(ClockInterface $clock): int
    {
        return (int) $clock->now()->format('Uv');
    }
}

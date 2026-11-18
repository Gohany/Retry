<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\RetryerInterface;
use Gohany\Retry\RetryPolicyInterface;
use Gohany\Retry\RetryResultInterface;
use Gohany\Retry\SleeperInterface;
use Gohany\Retry\Testing\TestAttemptContext;
use Gohany\Retry\Testing\TestOutcome;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

/** A retryer over the fixture Scheduler. Classifies nothing: errors carry no status or tags. */
final class Retryer implements RetryerInterface
{
    private readonly Scheduler $scheduler;

    public function __construct(
        private readonly SleeperInterface $sleeper,
        private readonly ClockInterface $clock,
        Randomizer $random,
    ) {
        $this->scheduler = new Scheduler($clock, $random);
    }

    public function try(callable $operation, RetryPolicyInterface $policy, array $context = []): mixed
    {
        $result = $this->tryOutcome($operation, $policy, $context);
        if ($result->isSuccess()) {
            return $result->value();
        }
        throw $result->lastError() ?? new \LogicException('A failed result has an error');
    }

    public function tryOutcome(callable $operation, RetryPolicyInterface $policy, array $context = []): RetryResultInterface
    {
        $scheduled = $policy->startAfterMs();
        if ($scheduled > 0) {
            $this->sleeper->sleepMs($scheduled);
        }
        $start = Scheduler::nowMs($this->clock);
        $deadline = $policy->deadlineBudgetMs() !== null ? $start + $policy->deadlineBudgetMs() : null;

        for ($attempt = 1; ; $attempt++) {
            $now = Scheduler::nowMs($this->clock);
            $remaining = $deadline !== null ? max(0, $deadline - $now) : null;
            $attemptContext = new TestAttemptContext($attempt, $policy->attempts(), $remaining, $scheduled, $now - $start, $context);
            try {
                return new Result(true, $operation($attemptContext), null, $attempt, null);
            } catch (\Throwable $e) {
                $now = Scheduler::nowMs($this->clock);
                $remaining = $deadline !== null ? max(0, $deadline - $now) : null;
                $decision = $this->scheduler->next(
                    $policy,
                    TestOutcome::failure(null, [], null, $e),
                    new TestAttemptContext($attempt, $policy->attempts(), $remaining, $scheduled, $now - $start, $context)
                );
                if (!$decision->retry()) {
                    return new Result(false, null, $e, $attempt, $decision->giveUpReason());
                }
                $scheduled = $decision->delayMs();
                $this->sleeper->sleepMs($scheduled);
            }
        }
    }
}

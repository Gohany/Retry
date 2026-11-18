<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * The pure scheduling call: given a policy and a failed attempt, retry after N ms or give up.
 *
 * It never sleeps. Durable retries (a row with nextAttemptAt, a queue message, a workflow
 * timer) call it and store the delay; RetryerInterface implementations call it between
 * attempts. Implementations take their clock and Randomizer by injection.
 */
interface RetrySchedulerInterface
{
    /**
     * @param AttemptOutcomeInterface $outcome The failed attempt (isSuccess() false).
     * @param AttemptContextInterface $context That attempt's context: attemptNumber() is the
     *                                         attempt that failed, remainingBudgetMs() the
     *                                         deadline budget left now.
     * @throws \InvalidArgumentException When $outcome is a success.
     */
    public function next(RetryPolicyInterface $policy, AttemptOutcomeInterface $outcome, AttemptContextInterface $context): RetryDecision;
}

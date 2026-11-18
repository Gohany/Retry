<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Why a retryer or scheduler stopped.
 */
enum GiveUpReason: string
{
    /** The decider said the failure is not retryable (e.g. a 4xx other than 408 and 429). */
    case NotRetryable = 'not_retryable';

    /** The last allowed attempt failed (key `a`, or a `seq` without `*` ran out). */
    case AttemptsExhausted = 'attempts_exhausted';

    /** The next attempt could not start before the deadline (key `dl`). */
    case DeadlineExceeded = 'deadline_exceeded';

    /** A RetryBudgetInterface refused the retry. */
    case BudgetExhausted = 'budget_exhausted';

    /** A CircuitBreakerInterface refused the attempt. */
    case CircuitOpen = 'circuit_open';
}

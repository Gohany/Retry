<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryDeciderInterface
{
    /** Whether a failed attempt is retryable. Never called for a successful outcome. */
    public function shouldRetry(AttemptOutcomeInterface $outcome, AttemptContextInterface $context): bool;
}

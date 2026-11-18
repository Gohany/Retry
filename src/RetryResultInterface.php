<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * What RetryerInterface::tryOutcome() reports.
 */
interface RetryResultInterface
{
    public function isSuccess(): bool;

    /** The operation's value on success, null otherwise. */
    public function value(): mixed;

    /** The last attempt's error (or the refusal, when no attempt ran), null on success. */
    public function lastError(): ?\Throwable;

    /** Attempts actually started (0 when a circuit breaker refused the first one). */
    public function attempts(): int;

    /** Why the retryer stopped, or null on success. */
    public function giveUpReason(): ?GiveUpReason;
}

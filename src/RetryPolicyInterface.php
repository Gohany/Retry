<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyInterface
{
    public function attempts(): int;

    public function attemptTimeoutMs(): ?int;

    public function deadlineBudgetMs(): ?int;

    public function startAfterMs(): int;

    public function hedgeEnabled(): bool;

    public function hedgeExtra(): int;

    public function hedgeDelayMs(): int;

    /**
     * Compute the nominal delay (before jitter) for the given attempt number.
     * For attempt #1, callers should use startAfterMs() instead.
     */
    public function nominalDelayMs(int $attemptNumber): int;

    public function jitter(): JitterSpecInterface;

    public function decider(): RetryDeciderInterface;

    public function canonicalSpec(): string;
}

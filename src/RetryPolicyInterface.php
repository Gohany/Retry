<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyInterface
{
    public function attempts(): int;

    public function attemptTimeoutMs(): ?int;

    public function deadlineBudgetMs(): ?int;

    public function startAfterMs(): int;

    public function hedge(): ?HedgeSpecInterface;
    public function followHeaders(): bool;

    public function nominalDelayMs(int $attemptNumber): int;

    public function jitter(): ?JitterSpecInterface;

    public function decider(): RetryDeciderInterface;

    public function canonicalSpec(): string;
}

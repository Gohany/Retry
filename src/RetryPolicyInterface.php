<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyInterface
{

    public function attempts(): int;

    public function attemptTimeoutMs(): ?int;

    public function deadlineBudgetMs(): ?int;

    public function startAfterMs(): int;

    public function delayMs(): ?int;

    public function followHeaders(): bool;

    public function capMs(): int;

    public function nextDelayMs(int $attemptNumber): int;

    public function sequence(): ?SequenceSpecInterface;

    public function hedge(): ?HedgeSpecInterface;

    public function jitter(): ?JitterSpecInterface;

    public function decider(): RetryDeciderInterface;

}
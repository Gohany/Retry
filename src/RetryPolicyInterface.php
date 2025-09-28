<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyInterface
{
    public function attempts(): int;

    public function attemptTimeoutMs(): ?int;

    public function deadlineBudgetMs(): ?int;

    public function startAfterMs(): int;

    public function followHeaders(): bool;

    public function nominalDelayMs(int $attemptNumber): int;

    public function sequence(): ?SequenceSpecInterface;

    public function hedge(): ?HedgeSpecInterface;

    public function jitter(): ?JitterSpecInterface;

    public function decider(): RetryDeciderInterface;

    public function canonicalSpec(): string;

    public function setAttempts(int $attempts): self;

    public function setAttemptTimeoutMs(?int $milliseconds): self;

    public function setDeadlineBudgetMs(?int $milliseconds): self;

    public function setStartAfterMs(int $milliseconds): self;

    public function setBackoffMode(string $mode): self;

    public function setExponentialBase(float $base): self;

    public function setIncrementMs(?int $milliseconds): self;

    public function setFollowHeaders(bool $follow): self;

    public function setSequence(SequenceSpecInterface $sequence): self;

    public function setHedgeSpec(HedgeSpecInterface $spec): self;

    public function setJitterSpec(JitterSpecInterface $jitter): self;

    public function setRetryDecider(RetryDeciderInterface $decider): self;

    public function setCanonicalSpec(string $specification): self;

    public function setRetryOnTokens(array $tokens): self;
}

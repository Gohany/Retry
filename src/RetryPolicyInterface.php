<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * An immutable retry policy. It holds no iteration state: every delay is a function of the
 * attempt number, so one instance is safe to share between requests and workers.
 */
interface RetryPolicyInterface
{
    /** Total attempts, including the first (key `a`, >= 1). */
    public function attempts(): int;

    /** Delay before attempt #1 in ms (key `sa`, default 0). Not jittered. */
    public function startAfterMs(): int;

    /** Base delay in ms (key `d`), or null in `seq` mode. */
    public function delayMs(): ?int;

    /**
     * Delay in ms before attempt $attemptNumber (>= 2), before cap and jitter.
     * exp: d * b^(n-2); lin: d * (n-1); seq: the sequence's delayMsAt(n).
     *
     * @throws \InvalidArgumentException When $attemptNumber < 2.
     */
    public function nominalDelayMs(int $attemptNumber): int;

    /** Upper bound applied to the nominal delay before jitter (key `cap`), or null for none. */
    public function capMs(): ?int;

    /** Jitter (key `j`). Never null: an absent `j` means full jitter, `j=100%@full`. */
    public function jitter(): JitterInterface;

    /** The explicit sequence in `seq` mode, otherwise null. */
    public function sequence(): ?SequenceInterface;

    /** Advisory per-attempt timeout in ms (key `t`), or null. */
    public function attemptTimeoutMs(): ?int;

    /** Overall budget in ms from the start of attempt #1 (key `dl`), or null. */
    public function deadlineBudgetMs(): ?int;

    /** Whether a server's Retry-After / RateLimit-Reset is honoured (key `fh`, default true). */
    public function followHeaders(): bool;

    /** Hedging hints (key `h`), or null. */
    public function hedge(): ?HedgeInterface;

    /** Decides whether a failed attempt is retryable. */
    public function decider(): RetryDeciderInterface;

    /** The canonical `rtry:` string (SPEC §6.4). */
    public function canonicalSpec(): string;
}

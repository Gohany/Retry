<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * What RetrySchedulerInterface::next() decided: retry after a delay, or give up and why.
 */
final class RetryDecision
{
    private function __construct(
        private readonly bool $retry,
        private readonly int $delayMs,
        private readonly ?GiveUpReason $giveUpReason,
    ) {
    }

    public static function retryAfter(int $delayMs): self
    {
        if ($delayMs < 0) {
            throw new \InvalidArgumentException('A retry delay cannot be negative: ' . $delayMs);
        }
        return new self(true, $delayMs, null);
    }

    public static function giveUp(GiveUpReason $reason): self
    {
        return new self(false, 0, $reason);
    }

    public function retry(): bool
    {
        return $this->retry;
    }

    /** Milliseconds to wait before the next attempt; 0 when giving up. */
    public function delayMs(): int
    {
        return $this->delayMs;
    }

    /** Null when retrying. */
    public function giveUpReason(): ?GiveUpReason
    {
        return $this->giveUpReason;
    }
}

<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\HedgeInterface;
use Gohany\Retry\JitterInterface;
use Gohany\Retry\RetryDeciderInterface;
use Gohany\Retry\RetryPolicyInterface;
use Gohany\Retry\SequenceInterface;

final class Policy implements RetryPolicyInterface
{
    public function __construct(
        private readonly int $attempts,
        private readonly string $mode,
        private readonly ?int $delayMs,
        private readonly float $base,
        private readonly ?Sequence $sequence,
        private readonly ?int $capMs,
        private readonly Jitter $jitter,
        private readonly ?int $attemptTimeoutMs,
        private readonly ?int $deadlineBudgetMs,
        private readonly int $startAfterMs,
        private readonly bool $followHeaders,
        private readonly ?Hedge $hedge,
        private readonly RetryDeciderInterface $decider,
        private readonly string $canonical,
    ) {
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function startAfterMs(): int
    {
        return $this->startAfterMs;
    }

    public function delayMs(): ?int
    {
        return $this->delayMs;
    }

    public function nominalDelayMs(int $attemptNumber): int
    {
        if ($attemptNumber < 2) {
            throw new \InvalidArgumentException('Nominal delays start at attempt 2');
        }
        return match ($this->mode) {
            'exp' => (int) round($this->delayMs * $this->base ** ($attemptNumber - 2)),
            'lin' => (int) $this->delayMs * ($attemptNumber - 1),
            'seq' => (int) $this->sequence?->delayMsAt($attemptNumber),
        };
    }

    public function capMs(): ?int
    {
        return $this->capMs;
    }

    public function jitter(): JitterInterface
    {
        return $this->jitter;
    }

    public function sequence(): ?SequenceInterface
    {
        return $this->sequence;
    }

    public function attemptTimeoutMs(): ?int
    {
        return $this->attemptTimeoutMs;
    }

    public function deadlineBudgetMs(): ?int
    {
        return $this->deadlineBudgetMs;
    }

    public function followHeaders(): bool
    {
        return $this->followHeaders;
    }

    public function hedge(): ?HedgeInterface
    {
        return $this->hedge;
    }

    public function decider(): RetryDeciderInterface
    {
        return $this->decider;
    }

    public function canonicalSpec(): string
    {
        return $this->canonical;
    }
}

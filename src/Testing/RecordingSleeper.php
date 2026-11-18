<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\SleeperInterface;

/**
 * Records every sleep instead of sleeping, and moves a FrozenClock forward by it.
 */
final class RecordingSleeper implements SleeperInterface
{
    /** @var list<int> */
    private array $sleeps = [];

    public function __construct(private readonly ?FrozenClock $clock = null)
    {
    }

    public function sleepMs(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
        $this->clock?->advanceMs(max(0, $milliseconds));
    }

    /** @return list<int> */
    public function sleeps(): array
    {
        return $this->sleeps;
    }
}

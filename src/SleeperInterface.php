<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * The only way a retryer waits. Tests inject one that records instead of sleeping.
 */
interface SleeperInterface
{
    public function sleepMs(int $milliseconds): void;
}

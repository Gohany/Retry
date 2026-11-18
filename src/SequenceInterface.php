<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * An explicit delay sequence (rtry key `seq`). Immutable: delays are looked up by position.
 */
interface SequenceInterface
{
    /** @return list<int> The configured delays in ms, the first one is the delay before attempt #2. */
    public function delaysMs(): array;

    /** Whether the last delay repeats once the sequence runs out (`*`). */
    public function isRepeatLastDelay(): bool;

    /**
     * Delay in ms before attempt $attemptNumber (>= 2): delaysMs()[$attemptNumber - 2], the last
     * delay past the end when it repeats, otherwise null.
     */
    public function delayMsAt(int $attemptNumber): ?int;
}

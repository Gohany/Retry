<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Hedging hints (rtry key `h`). Advisory: this package defines no hedging runtime.
 */
interface HedgeInterface
{
    public const CANCEL_ON_FIRST_SUCCESS = 0;
    public const CANCEL_ON_FIRST_COMPLETION = 1;

    /** One of CANCEL_ON_FIRST_SUCCESS, CANCEL_ON_FIRST_COMPLETION. */
    public function cancelPolicy(): int;

    /**
     * Fixed delay (ms) between consecutive lane starts.
     * Example: lanes=3, stagger=200 gives offsets [0, 200, 400].
     */
    public function staggerDelayMs(): int;

    /** Total lanes, including the first. 1 means hedging is effectively off. */
    public function lanes(): int;
}

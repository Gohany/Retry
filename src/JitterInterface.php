<?php declare(strict_types=1);

namespace Gohany\Retry;

use Random\Randomizer;

/**
 * How a nominal delay is randomised (rtry key `j`). See SPEC §6.2 for the bounds.
 */
interface JitterInterface
{
    public const MODE_FULL = 'full';
    public const MODE_PM = 'pm';
    public const MODE_NONE = 'none';

    /** One of MODE_FULL, MODE_PM, MODE_NONE. */
    public function mode(): string;

    /** Window as a percent of the nominal delay, 0-100, or null when the window is absolute. */
    public function percent(): ?float;

    /** Absolute window in ms, or null when the window is a percent. */
    public function windowMs(): ?int;

    /**
     * Return the randomised delay (>= 0) for a nominal delay.
     *
     * Draws exactly one value with $random->getInt($low, $high) when $low < $high and none
     * otherwise, so a seeded Randomizer gives the same schedule in every implementation.
     */
    public function apply(int $nominalDelayMs, Randomizer $random): int;
}

<?php

namespace Gohany\Retry;

interface HedgeSpecInterface
{
    public const CANCEL_ON_FIRST_SUCCESS     = 'FIRST_SUCCESS';     // cancel others when first success occurs
    public const CANCEL_ON_FIRST_COMPLETION  = 'FIRST_COMPLETION';  // cancel others when any

    /**
     * Policy determining when other in-flight lanes should be cancelled.
     * One of: CANCEL_ON_FIRST_SUCCESS, CANCEL_ON_FIRST_COMPLETION
     */
    public function getCancelPolicy(): string;

    /**
     * Fixed delay (in milliseconds) between consecutive lane starts.
     * Example: lanes=3, stagger=200 -> offsets: [0, 200, 400] (before jitter).
     */
    public function getStaggerDelayMs(): int;

    /**
     * Total number of lanes to schedule.
     * Lane 1 starts at t=0 (immediately). If lanes == 1, hedging is effectively disabled.
     */
    public function getLanes(): int;

}
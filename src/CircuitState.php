<?php declare(strict_types=1);

namespace Gohany\Retry;

enum CircuitState: string
{
    /** Calls flow; failures are counted. */
    case Closed = 'closed';

    /** Calls are refused until the open period ends. */
    case Open = 'open';

    /** One probe call is let through; its result closes or re-opens the circuit. */
    case HalfOpen = 'half_open';
}

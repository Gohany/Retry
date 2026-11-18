<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\AttemptContextInterface;
use Gohany\Retry\AttemptOutcomeInterface;
use Gohany\Retry\RetryDeciderInterface;

/**
 * A decider that always says the same thing, for tests that are not about classification.
 */
final class FixedDecider implements RetryDeciderInterface
{
    private function __construct(private readonly bool $retry)
    {
    }

    public static function always(): self
    {
        return new self(true);
    }

    public static function never(): self
    {
        return new self(false);
    }

    public function shouldRetry(AttemptOutcomeInterface $outcome, AttemptContextInterface $context): bool
    {
        return $this->retry;
    }
}

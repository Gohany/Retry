<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyFactoryInterface
{
    /**
     * Parse and validate an `rtry:` string (SPEC §6).
     *
     * @param RetryDeciderInterface|null $decider Replaces the decider the `on` key would build.
     * @throws \InvalidArgumentException When the string is not valid.
     */
    public function fromSpec(string $spec, ?RetryDeciderInterface $decider = null): RetryPolicyInterface;
}

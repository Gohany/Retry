<?php declare(strict_types=1);

namespace Gohany\Retry;

interface RetryPolicyFactoryInterface
{
    /** @throws \InvalidArgumentException on malformed spec. */
    public function fromSpec(string $spec): RetryPolicyInterface;
}

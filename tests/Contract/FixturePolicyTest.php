<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\Testing\RetryPolicyContractTestCase;
use Gohany\Retry\Tests\Fixtures\PolicyFactory;

final class FixturePolicyTest extends RetryPolicyContractTestCase
{
    protected function createPolicyFactory(): RetryPolicyFactoryInterface
    {
        return new PolicyFactory();
    }
}

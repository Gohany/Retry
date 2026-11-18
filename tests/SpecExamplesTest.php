<?php declare(strict_types=1);

namespace Gohany\Retry\Tests;

use Gohany\Retry\Tests\Fixtures\PolicyFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every rtry: example in README.md and SPEC.md is valid under SPEC §6, not just made of
 * grammar keys.
 */
final class SpecExamplesTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function examples(): iterable
    {
        return SpecConformanceTest::rtryExamples();
    }

    #[DataProvider('examples')]
    public function testExampleParses(string $example): void
    {
        $policy = (new PolicyFactory())->fromSpec($example);
        self::assertStringStartsWith('rtry:', $policy->canonicalSpec());
    }
}

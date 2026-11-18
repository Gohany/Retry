<?php declare(strict_types=1);

namespace Gohany\Retry\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps SPEC.md, README.md and the code in src/ from drifting apart.
 *
 * - Every public type in src/ (outside src/Testing) has a "### 5.x Name" section in SPEC §5,
 *   and every such section names a type that exists.
 * - The methods (and enum cases) a section lists are exactly the ones the type declares.
 * - The rtry: grammar keys in SPEC §6 are exactly the keys Rtry 1.x parses, the canonical
 *   order lists each of them once, and every rtry: example in README.md and SPEC.md uses
 *   only grammar keys, each at most once.
 */
final class SpecConformanceTest extends TestCase
{
    /** The keys Gohany/Rtry 1.x RtryPolicyFactory::makePart() accepts. */
    private const RTRY_1X_KEYS = ['a', 'b', 'cap', 'd', 'dl', 'fh', 'h', 'j', 'm', 'on', 'sa', 'seq', 't'];

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    private static function spec(): string
    {
        return (string) file_get_contents(self::root() . '/SPEC.md');
    }

    private static function readme(): string
    {
        return (string) file_get_contents(self::root() . '/README.md');
    }

    /** Text of the "## <n>." section of SPEC.md, up to the next "## " heading. */
    private static function specSection(int $n): string
    {
        $spec = self::spec();
        if (!preg_match('/^## ' . $n . '\.[^\n]*\n(.*?)(?=^## |\z)/ms', $spec, $m)) {
            self::fail('SPEC.md has no "## ' . $n . '." section');
        }
        return $m[1];
    }

    /**
     * SPEC §5 subsections keyed by type short name.
     *
     * @return array<string,string>
     */
    private static function documentedTypes(): array
    {
        preg_match_all('/^### 5\.\d+ (\w+)\s*\n(.*?)(?=^### |\z)/ms', self::specSection(5), $m, PREG_SET_ORDER);
        $types = [];
        foreach ($m as $match) {
            $types[$match[1]] = $match[2];
        }
        return $types;
    }

    /** @return list<string> */
    private static function sourceTypes(): array
    {
        $types = [];
        foreach (glob(self::root() . '/src/*.php') ?: [] as $file) {
            $types[] = basename($file, '.php');
        }
        sort($types);
        return $types;
    }

    public function testEveryPublicTypeIsInSpecSectionFiveAndViceVersa(): void
    {
        $documented = array_keys(self::documentedTypes());
        sort($documented);

        self::assertSame(self::sourceTypes(), $documented, 'SPEC §5 sections must match the types in src/ one to one');
    }

    /** @return iterable<string,array{string}> */
    public static function documentedTypeNames(): iterable
    {
        foreach (array_keys(self::documentedTypes()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('documentedTypeNames')]
    public function testSpecListsExactlyTheMethodsTheTypeDeclares(string $name): void
    {
        $fqcn = 'Gohany\\Retry\\' . $name;
        self::assertTrue(
            interface_exists($fqcn) || class_exists($fqcn) || enum_exists($fqcn),
            "SPEC §5 documents {$name}, but {$fqcn} does not exist"
        );

        $section = self::documentedTypes()[$name];
        preg_match_all('/^- `(\w+)\(/m', $section, $mm);
        $documentedMethods = array_values(array_unique($mm[1]));
        sort($documentedMethods);

        $type = new \ReflectionClass($fqcn);
        $declared = [];
        foreach ($type->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $fqcn || $method->isConstructor()) {
                continue;
            }
            if ($type->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
                continue;
            }
            $declared[] = $method->getName();
        }
        sort($declared);

        self::assertSame($declared, $documentedMethods, "SPEC §5 {$name} must list exactly the methods {$fqcn} declares");

        if ($type->isEnum()) {
            preg_match_all('/^- case `(\w+)`/m', $section, $cm);
            $documentedCases = $cm[1];
            sort($documentedCases);
            $cases = array_map(static fn (\UnitEnum $c): string => $c->name, $fqcn::cases());
            sort($cases);
            self::assertSame($cases, $documentedCases, "SPEC §5 {$name} must list exactly the enum's cases");
        }
    }

    /** @return list<string> */
    private static function grammarKeys(): array
    {
        preg_match_all('/^- \*\*(\w+)\*\*/m', self::specSection(6), $m);
        return $m[1];
    }

    public function testGrammarKeysAreExactlyTheKeysRtryOneParses(): void
    {
        $keys = self::grammarKeys();
        self::assertSame(count($keys), count(array_unique($keys)), 'SPEC §6 defines a key more than once');
        sort($keys);
        self::assertSame(self::RTRY_1X_KEYS, $keys);
    }

    public function testCanonicalOrderListsEveryKeyOnce(): void
    {
        self::assertMatchesRegularExpression('/^\*\*Canonical key order\*\*: `([^`]+)`/m', self::specSection(6));
        preg_match('/^\*\*Canonical key order\*\*: `([^`]+)`/m', self::specSection(6), $m);
        $order = array_map('trim', explode(';', $m[1]));
        $sorted = $order;
        sort($sorted);
        self::assertSame(self::RTRY_1X_KEYS, $sorted, 'the canonical order must list every grammar key exactly once');
    }

    /** @return iterable<string,array{string}> */
    public static function rtryExamples(): iterable
    {
        foreach (['README.md' => self::readme(), 'SPEC.md' => self::spec()] as $file => $text) {
            preg_match_all('/rtry:[^\s\'"`]+/', $text, $m);
            foreach (array_unique($m[0]) as $example) {
                if (str_contains($example, '<')) {
                    continue; // the grammar template itself
                }
                yield $file . ' ' . $example => [$example];
            }
        }
    }

    #[DataProvider('rtryExamples')]
    public function testExamplesUseOnlyGrammarKeys(string $example): void
    {
        $keys = [];
        foreach (explode(';', substr($example, strlen('rtry:'))) as $pair) {
            if ($pair === '') {
                continue;
            }
            self::assertStringContainsString('=', $pair, "malformed pair '{$pair}' in {$example}");
            $keys[] = strtolower(strstr($pair, '=', true));
        }
        self::assertSame([], array_values(array_diff($keys, self::grammarKeys())), "{$example} uses keys outside the grammar");
        self::assertSame(count($keys), count(array_unique($keys)), "{$example} repeats a key");
    }

    public function testReadmeHasExamplesToCheck(): void
    {
        self::assertNotEmpty(iterator_to_array(self::rtryExamples()));
        self::assertStringContainsString('rtry:', self::readme());
    }

    public function testReadmeNamesNoForeignNamespaces(): void
    {
        self::assertStringNotContainsStringIgnoringCase('foreup', self::readme());
        self::assertStringNotContainsString('Psr\\Retry', self::readme());
    }
}

<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeded Randomizers, and the delays SPEC §5.5 says a seeded jitter must produce.
 */
final class SeededRandom
{
    public static function randomizer(int $seed): Randomizer
    {
        return new Randomizer(new Mt19937($seed));
    }

    /**
     * Replays SPEC §5.5: one getInt($low, $high) per bound pair with low < high, none otherwise.
     *
     * @param list<array{0:int,1:int}> $bounds
     * @return list<int>
     */
    public static function expectedDraws(int $seed, array $bounds): array
    {
        $random = self::randomizer($seed);
        $out = [];
        foreach ($bounds as [$low, $high]) {
            $out[] = $low < $high ? $random->getInt($low, $high) : $low;
        }
        return $out;
    }
}

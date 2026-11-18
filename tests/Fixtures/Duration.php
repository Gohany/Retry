<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

final class Duration
{
    public static function parseMs(string $token): int
    {
        if (!preg_match('~^([0-9]+(?:\.[0-9]+)?)(ms|s|m|h)?$~i', trim($token), $m)) {
            throw new \InvalidArgumentException('Invalid duration: ' . $token);
        }
        $factor = match (strtolower($m[2] ?? 'ms')) {
            'ms', '' => 1,
            's' => 1000,
            'm' => 60_000,
            'h' => 3_600_000,
        };
        return (int) round((float) $m[1] * $factor);
    }
}

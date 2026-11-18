<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\RetryDeciderInterface;
use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\RetryPolicyInterface;

/** A small SPEC §6 parser, to prove the contracts can be met. Not for production. */
final class PolicyFactory implements RetryPolicyFactoryInterface
{
    private const ORDER = ['a', 'd', 'm', 'b', 'seq', 'cap', 'j', 't', 'dl', 'on', 'sa', 'fh', 'h'];

    public function fromSpec(string $spec, ?RetryDeciderInterface $decider = null): RetryPolicyInterface
    {
        $s = trim($spec);
        if (stripos($s, 'rtry:') === 0) {
            $s = substr($s, 5);
        }
        $raw = [];
        foreach (explode(';', $s) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            if (!str_contains($pair, '=')) {
                throw new \InvalidArgumentException('Malformed pair: ' . $pair);
            }
            [$k, $v] = array_map('trim', explode('=', $pair, 2));
            $k = strtolower($k);
            if (!in_array($k, self::ORDER, true)) {
                throw new \InvalidArgumentException('Unknown key: ' . $k);
            }
            if (array_key_exists($k, $raw)) {
                throw new \InvalidArgumentException('Duplicate key: ' . $k);
            }
            $raw[$k] = $v;
        }

        if (!isset($raw['a']) || !preg_match('/^\d+$/', $raw['a']) || (int) $raw['a'] < 1) {
            throw new \InvalidArgumentException('a must be an integer >= 1');
        }
        $attempts = (int) $raw['a'];

        $mode = strtolower($raw['m'] ?? 'exp');
        if (!in_array($mode, ['exp', 'lin', 'seq'], true)) {
            throw new \InvalidArgumentException('Unknown mode: ' . $mode);
        }
        $delay = isset($raw['d']) ? Duration::parseMs($raw['d']) : null;
        if ($mode === 'seq' ? $delay !== null : $delay === null) {
            throw new \InvalidArgumentException('d is required for exp and lin, and rejected for seq');
        }
        $sequence = isset($raw['seq']) ? Sequence::parse($raw['seq']) : null;
        if (($mode === 'seq') !== ($sequence !== null)) {
            throw new \InvalidArgumentException('seq is required in seq mode and rejected otherwise');
        }
        if ($sequence !== null && !$sequence->isRepeatLastDelay() && $attempts > count($sequence->delaysMs()) + 1) {
            throw new \InvalidArgumentException('seq is too short for a');
        }
        if (isset($raw['b']) && ($mode !== 'exp' || !is_numeric($raw['b']) || (float) $raw['b'] < 1)) {
            throw new \InvalidArgumentException('b must be >= 1, exp mode only');
        }
        $base = (float) ($raw['b'] ?? 2);

        $jitter = isset($raw['j']) ? Jitter::parse($raw['j']) : new Jitter(Jitter::MODE_FULL, 100.0, null);

        if (isset($raw['fh']) && !in_array($raw['fh'], ['0', '1'], true)) {
            throw new \InvalidArgumentException('fh must be 0 or 1');
        }
        $tokens = isset($raw['on']) ? array_values(array_filter(array_map('trim', explode(',', $raw['on'])), static fn ($t) => $t !== '')) : ['default'];
        if ($tokens === []) {
            throw new \InvalidArgumentException('on needs at least one token');
        }
        $hedge = isset($raw['h']) ? Hedge::parse($raw['h']) : null;

        $values = [
            'a' => (string) $attempts,
            'd' => $delay !== null ? (string) $delay : null,
            'm' => $mode,
            'b' => isset($raw['b']) ? Jitter::number($base) : null,
            'seq' => $sequence?->canonical(),
            'cap' => isset($raw['cap']) ? (string) Duration::parseMs($raw['cap']) : null,
            'j' => $jitter->canonical(),
            't' => isset($raw['t']) ? (string) Duration::parseMs($raw['t']) : null,
            'dl' => isset($raw['dl']) ? (string) Duration::parseMs($raw['dl']) : null,
            'on' => implode(',', $tokens),
            'sa' => isset($raw['sa']) ? (string) Duration::parseMs($raw['sa']) : null,
            'fh' => $raw['fh'] ?? null,
            'h' => $hedge?->canonical(),
        ];
        $canonical = [];
        foreach (self::ORDER as $key) {
            if (array_key_exists($key, $raw)) {
                $canonical[] = $key . '=' . $values[$key];
            }
        }

        return new Policy(
            $attempts,
            $mode,
            $delay,
            $base,
            $sequence,
            isset($raw['cap']) ? Duration::parseMs($raw['cap']) : null,
            $jitter,
            isset($raw['t']) ? Duration::parseMs($raw['t']) : null,
            isset($raw['dl']) ? Duration::parseMs($raw['dl']) : null,
            isset($raw['sa']) ? Duration::parseMs($raw['sa']) : 0,
            ($raw['fh'] ?? '1') === '1',
            $hedge,
            $decider ?? new TokenDecider($tokens),
            'rtry:' . implode(';', $canonical),
        );
    }
}

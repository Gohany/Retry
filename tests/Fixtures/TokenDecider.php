<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\AttemptContextInterface;
use Gohany\Retry\AttemptOutcomeInterface;
use Gohany\Retry\RetryDeciderInterface;

/** SPEC §6.3. */
final class TokenDecider implements RetryDeciderInterface
{
    public const DEFAULT = ['408', '429', '5xx', 'ETIMEDOUT', 'ECONNRESET', 'ECONNREFUSED', 'NETWORK_ERROR', 'DEADLOCK', 'RATE_LIMITED'];

    /** @var list<string> */
    private array $tokens = [];

    /** @param list<string> $tokens */
    public function __construct(array $tokens)
    {
        foreach ($tokens as $token) {
            $t = strtoupper($token);
            if ($t === 'DEFAULT') {
                array_push($this->tokens, ...array_map('strtoupper', self::DEFAULT));
            } else {
                $this->tokens[] = $t;
            }
        }
    }

    public function shouldRetry(AttemptOutcomeInterface $outcome, AttemptContextInterface $context): bool
    {
        $status = $outcome->statusCode();
        $tags = array_map('strtoupper', $outcome->tags());
        foreach ($this->tokens as $token) {
            if (preg_match('/^\d{3}$/', $token) && $status === (int) $token) {
                return true;
            }
            if (preg_match('/^(\d)XX$/', $token, $m) && $status !== null && intdiv($status, 100) === (int) $m[1]) {
                return true;
            }
            if (in_array($token, $tags, true)) {
                return true;
            }
        }
        return false;
    }
}

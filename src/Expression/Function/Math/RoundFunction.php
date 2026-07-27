<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * ROUND: Round value to N decimals.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: ROUND(value, decimals)
 */
class RoundFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'ROUND';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $num = $this->parseToFloat($value);
        if ($num === null) {
            return '';
        }

        $decimals = isset($params[0]) ? ($this->parseToFloat($params[0]) ?? 0) : 0;

        return round($num, (int) $decimals);
    }
}

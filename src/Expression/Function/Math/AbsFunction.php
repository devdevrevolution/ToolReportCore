<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * ABS: Absolute value of value.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: ABS(value)
 */
class AbsFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'ABS';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $num = $this->parseToFloat($value);
        if ($num === null) {
            return '';
        }

        return abs($num);
    }
}

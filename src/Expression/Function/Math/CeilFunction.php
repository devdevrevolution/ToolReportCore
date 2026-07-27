<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * CEIL: Ceiling of value.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: CEIL(value)
 */
class CeilFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'CEIL';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $num = $this->parseToFloat($value);
        if ($num === null) {
            return '';
        }

        return ceil($num);
    }
}

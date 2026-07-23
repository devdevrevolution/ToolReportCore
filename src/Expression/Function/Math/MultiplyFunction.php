<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * MULTIPLY: Product of value × all params.
 *
 * Usage: MULTIPLY(a, b) or MULTIPLY(value, 2, 3)
 */
class MultiplyFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'MULTIPLY';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        $result = (float) $value;

        foreach ($params as $param) {
            if (is_numeric($param)) {
                $result *= (float) $param;
            }
        }

        return $result;
    }
}

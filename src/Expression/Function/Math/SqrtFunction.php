<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * SQRT: Square root of value. Negative → empty string.
 *
 * Usage: SQRT(value)
 */
class SqrtFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'SQRT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        $num = (float) $value;

        if ($num < 0) {
            return '';
        }

        return sqrt($num);
    }
}

<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * ABS: Absolute value of value.
 *
 * Usage: ABS(value)
 */
class AbsFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'ABS';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        return abs((float) $value);
    }
}

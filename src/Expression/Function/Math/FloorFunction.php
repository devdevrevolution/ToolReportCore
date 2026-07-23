<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * FLOOR: Floor of value.
 *
 * Usage: FLOOR(value)
 */
class FloorFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'FLOOR';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        return floor((float) $value);
    }
}

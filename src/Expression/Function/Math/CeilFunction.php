<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * CEIL: Ceiling of value.
 *
 * Usage: CEIL(value)
 */
class CeilFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'CEIL';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        return ceil((float) $value);
    }
}

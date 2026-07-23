<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * ROUND: Round value to N decimals.
 *
 * Usage: ROUND(value, decimals)
 */
class RoundFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'ROUND';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        $decimals = isset($params[0]) && is_numeric($params[0]) ? (int) $params[0] : 0;

        return round((float) $value, $decimals);
    }
}

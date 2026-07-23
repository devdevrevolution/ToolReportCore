<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * POW: value ^ first param.
 *
 * Usage: POW(base, exponent)
 */
class PowFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'POW';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value) || !isset($params[0]) || !is_numeric($params[0])) {
            return '';
        }

        return pow((float) $value, (float) $params[0]);
    }
}

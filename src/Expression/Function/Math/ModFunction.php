<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * MOD: value % first param. Zero divisor → empty string.
 *
 * Usage: MOD(a, b)
 */
class ModFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'MOD';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value) || !isset($params[0]) || !is_numeric($params[0])) {
            return '';
        }

        $divisor = (float) $params[0];

        if ($divisor === 0.0) {
            return '';
        }

        return fmod((float) $value, $divisor);
    }
}

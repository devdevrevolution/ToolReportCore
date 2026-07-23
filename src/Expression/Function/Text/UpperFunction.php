<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * UPPER: Convert string to uppercase.
 *
 * Usage: UPPER(value)
 */
class UpperFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'UPPER';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        return mb_strtoupper((string) $value);
    }
}

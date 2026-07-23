<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * LOWER: Convert string to lowercase.
 *
 * Usage: LOWER(value)
 */
class LowerFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'LOWER';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower((string) $value);
    }
}

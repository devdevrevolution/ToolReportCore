<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * TRIM: Trim whitespace from both ends of a string.
 *
 * Usage: TRIM(value)
 */
class TrimFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'TRIM';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}

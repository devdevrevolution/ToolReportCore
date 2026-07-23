<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * REPLACE: Replace all occurrences of a search string.
 *
 * Usage: REPLACE(value, search, replace)
 */
class ReplaceFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'REPLACE';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $search = (string) ($params[0] ?? '');
        $replace = (string) ($params[1] ?? '');

        return str_replace($search, $replace, (string) $value);
    }
}

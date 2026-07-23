<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * SUBSTR: Extract a substring.
 *
 * Usage: SUBSTR(value, start, length)
 */
class SubstrFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'SUBSTR';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        $start = (int) ($params[0] ?? 0);
        $length = isset($params[1]) ? (int) $params[1] : null;

        if ($length !== null) {
            return mb_substr($string, $start, $length);
        }

        return mb_substr($string, $start);
    }
}

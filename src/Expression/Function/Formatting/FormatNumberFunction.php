<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Formatting;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * FORMAT_NUMBER: Format a number with separators.
 *
 * Usage: FORMAT_NUMBER(value, decimals, dec_sep, thousands_sep)
 */
class FormatNumberFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'FORMAT_NUMBER';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        $decimals = (int) ($params[0] ?? 0);
        $decimalSeparator = (string) ($params[1] ?? '.');
        $thousandsSeparator = (string) ($params[2] ?? ',');

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }
}

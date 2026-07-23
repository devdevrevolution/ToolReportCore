<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Date;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * DATE_DIFF: Difference between two dates in days.
 *
 * Usage: DATE_DIFF(date1, date2)
 */
class DateDiffFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'DATE_DIFF';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null || !isset($params[0])) {
            return '';
        }

        $ts1 = is_numeric($value) ? (int) $value : strtotime((string) $value);
        $ts2 = is_numeric($params[0]) ? (int) $params[0] : strtotime((string) $params[0]);

        if ($ts1 === false || $ts2 === false) {
            return '';
        }

        $diff = abs($ts2 - $ts1);

        return (int) floor($diff / 86400);
    }
}

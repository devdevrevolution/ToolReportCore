<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * MAX: Maximum of value and all params.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: MAX(a, b, c) or MAX(values[])
 */
class MaxFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'MAX';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $numbers = $this->collectNumbers($value, $params);

        if ($numbers === []) {
            return '';
        }

        return max($numbers);
    }

    /**
     * @return list<float>
     */
    private function collectNumbers(mixed $value, array $params): array
    {
        $numbers = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                $parsed = $this->parseToFloat($item);
                if ($parsed !== null) {
                    $numbers[] = $parsed;
                }
            }
        } else {
            $parsed = $this->parseToFloat($value);
            if ($parsed !== null) {
                $numbers[] = $parsed;
            }
        }

        foreach ($params as $param) {
            $parsed = $this->parseToFloat($param);
            if ($parsed !== null) {
                $numbers[] = $parsed;
            }
        }

        return $numbers;
    }
}

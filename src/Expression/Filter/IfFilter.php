<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Conditional filter: return one value if the resolved value matches, another if not.
 *
 * Usage: {{ value | if(compare, true_result, false_result) }}
 *
 * Examples:
 *   {{ status | if("active", "Activo", "Inactivo") }}  → "Activo" when status === "active"
 *   {{ is_paid | if(true, "Pagado", "Pendiente") }}     → "Pagado" when is_paid === true
 *   {{ qty | if(0, "Sin stock", "Disponible") }}         → "Sin stock" when qty === 0
 */
class IfFilter implements FilterInterface
{
    public function name(): string
    {
        return 'if';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        $compare = $params[0] ?? null;
        $trueResult = $params[1] ?? '';
        $falseResult = $params[2] ?? '';

        // Normalize comparison: treat numeric strings as numbers for comparison
        if (is_numeric($value) && is_numeric($compare)) {
            return ((float) $value === (float) $compare) ? $trueResult : $falseResult;
        }

        // Handle boolean comparison
        if ($compare === 'true') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $trueResult : $falseResult;
        }

        if ($compare === 'false') {
            return ! filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $trueResult : $falseResult;
        }

        return ($value === $compare) ? $trueResult : $falseResult;
    }
}
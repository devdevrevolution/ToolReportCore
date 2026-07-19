<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout;

use Toolreport\Core\Expression\ExpressionParser;
use Toolreport\Core\Expression\FilterRegistry;

/**
 * Trait for interpolating {{variable}} placeholders with local-first resolution.
 *
 * Resolves variables by checking $localData first, then $data.
 * Unresolved placeholders are left as-is in the output.
 *
 * Supports bracket notation for collection items:
 *   - {{ [].cognome }}      → resolves 'cognome' from $localData (current iteration item)
 *   - {{ orders[].total }}  → resolves 'total' from $localData (current iteration item)
 *   - {{ [0].cognome }}     → resolves 'cognome' from $data[0] (specific index in global data)
 *   - {{ orders[0].total }} → resolves 'total' from $data['orders'][0] (specific index in nested)
 *   - {{ name }}            → local-first, then global fallback
 *   - {{ client.name }}     → nested dot-notation, local-first, then global
 *
 * Supports filter expressions via pipe syntax:
 *   - {{ price | currency("$") }}                     → "$1,234.56"
 *   - {{ total | number(2, ",", ".") }}                → "1.234,56"
 *   - {{ name | upper }}                               → "JOHN DOE"
 *   - {{ status | if("active", "Activo", "Inactivo") }} → "Activo"
 *   - {{ name | trim | upper }}                        → chained filters
 *
 * Supports concatenation with + and literal strings:
 *   - {{ 'Total: ' + price | currency("$") }}          → "Total: $1,234.56"
 *   - {{ name | upper + '!' }}                          → "JOHN DOE!"
 *   - {{ 'Richiesta:\n' + house.price | currency("$") }} → "Richiesta:\n$1,234.56"
 *   - {{ '[' + code + '] ' + name }}                    → "[ABC] Widget"
 */
trait InterpolatesVariables
{
    /**
     * Lazily initialized filter registry.
     */
    private ?FilterRegistry $filterRegistry = null;

    /**
     * Get the filter registry, initializing with defaults on first access.
     */
    protected function getFilterRegistry(): FilterRegistry
    {
        if ($this->filterRegistry === null) {
            $this->filterRegistry = new FilterRegistry();
            $this->filterRegistry->registerDefaults();
        }

        return $this->filterRegistry;
    }

    /**
     * Replace {{variable}} placeholders in text using local-first resolution.
     *
     * For each placeholder:
     *   - Keys with [] notation ([].) are resolved from localData (current iteration item).
     *   - Keys with [N] notation ([N]) are resolved from global data (specific index).
     *   - Plain keys are checked in localData first, then fall back to data.
     *   - If not found in either, the placeholder is left unchanged.
     *   - Pipe syntax applies filters: {{ price | currency("$") }}
     *   - Plus syntax concatenates: {{ 'Total: ' + price | currency("$") }}
     *   - Quoted strings are treated as literals: {{ 'Hello\nWorld' }}
     */
    protected function interpolate(string $text, array $data, array $localData = []): string
    {
        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($matches) use ($data, $localData) {
            $expression = $matches[1];

            // If the expression contains pipes, plus, or quoted strings → use ExpressionParser
            if (ExpressionParser::isExpression($expression)) {
                $parsed = ExpressionParser::parse($expression);

                // Single variable without concatenation: backward compat path
                if (!$parsed->hasConcatenation && count($parsed->terms) === 1 && $parsed->terms[0]['type'] === 'variable') {
                    $term = $parsed->terms[0];
                    $value = $this->resolveVariableKey($term['value'], $data, $localData);

                    // If variable could not be resolved and has no default filter, leave placeholder
                    if ($value === null && $term['filters'] === []) {
                        return $matches[0];
                    }

                    // Apply filters in chain
                    foreach ($term['filters'] as $filterDef) {
                        if (!$this->getFilterRegistry()->has($filterDef['name'])) {
                            continue;
                        }
                        $filter = $this->getFilterRegistry()->get($filterDef['name']);
                        $value = $filter->apply($value, $filterDef['params']);
                    }

                    return (string) ($value ?? $matches[0]);
                }

                // Concatenation or mixed expression: evaluate each term
                $result = '';
                foreach ($parsed->terms as $term) {
                    if ($term['type'] === 'literal') {
                        $result .= $term['value'];
                    } else {
                        $value = $this->resolveVariableKey($term['value'], $data, $localData);

                        // In concatenation context, unresolved variables become empty string
                        if ($value === null) {
                            $value = '';
                        }

                        // Apply filters
                        foreach ($term['filters'] as $filterDef) {
                            if (!$this->getFilterRegistry()->has($filterDef['name'])) {
                                continue;
                            }
                            $filter = $this->getFilterRegistry()->get($filterDef['name']);
                            $value = $filter->apply($value, $filterDef['params']);
                        }

                        $result .= (string) ($value ?? '');
                    }
                }

                return $result;
            }

            // Legacy path: plain variable without filters or expressions
            // Backward compatible regex: only word chars, dots, brackets
            if (!preg_match('/^[\w.\[\]]+$/', $expression)) {
                return $matches[0]; // Leave complex expressions unchanged
            }

            return (string) ($this->resolveVariableKey($expression, $data, $localData) ?? $matches[0]);
        }, $text);
    }

    /**
     * Resolve a variable key, handling bracket notation for collection items.
     *
     * Bracket notation semantics:
     *   - [].fieldName          → resolve fieldName from localData (current iteration item)
     *   - parent[].fieldName    → resolve fieldName from localData (current iteration item)
     *   - [N]                   → resolve entire item at $data[N] (specific index)
     *   - [N].fieldName         → resolve fieldName from $data[N]
     *   - parent[N]             → resolve entire item at $data['parent'][N]
     *   - parent[N].fieldName   → resolve fieldName from $data['parent'][N]
     *   - fieldName             → localData first, then global fallback
     *   - parent.child          → nested dot-notation, local-first, then global
     */
    protected function resolveVariableKey(string $key, array $data, array $localData): mixed
    {
        // Handle [].fieldName — strip [] prefix, resolve from localData only
        if (str_starts_with($key, '[].')) {
            $fieldPath = substr($key, 3);

            return $this->arrayGet($localData, $fieldPath);
        }

        // Handle parent[].fieldName — strip everything up to [], resolve from localData only
        // When inside a detail band iteration, localData IS the current item
        if (str_contains($key, '[].')) {
            $parts = explode('[].', $key, 2);
            $fieldPath = $parts[1];

            return $this->arrayGet($localData, $fieldPath);
        }

        // Handle [N] or [N].fieldName — specific numeric index in global data
        // e.g. [0].name → $data[0]['name'], [0] → $data[0]
        if (preg_match('/^\[(\d+)\](?:\.(.+))?$/', $key, $m)) {
            $index = (int) $m[1];

            if (! isset($data[$index])) {
                return null;
            }

            if (isset($m[2]) && $m[2] !== '') {
                return $this->arrayGet($data[$index], $m[2]);
            }

            return $data[$index];
        }

        // Handle parent[N] or parent[N].fieldName — specific index in nested data
        // e.g. orders[0].name → $data['orders'][0]['name'], orders[0] → $data['orders'][0]
        if (preg_match('/^(\w+)\[(\d+)\](?:\.(.+))?$/', $key, $m)) {
            $parentKey = $m[1];
            $index = (int) $m[2];

            if (! isset($data[$parentKey][$index])) {
                return null;
            }

            if (isset($m[3]) && $m[3] !== '') {
                return $this->arrayGet($data[$parentKey][$index], $m[3]);
            }

            return $data[$parentKey][$index];
        }

        // Standard resolution: localData first, then global data
        return $this->arrayGet($localData, $key) ?? $this->arrayGet($data, $key);
    }

    /**
     * Resolve a dot-notation key from an array.
     *
     * e.g., "client.name" → $data['client']['name']
     */
    protected function arrayGet(array $data, string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

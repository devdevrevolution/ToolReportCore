<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Parse expression strings with concatenation and filters.
 *
 * Supports:
 *   - Plain variables:                    "name"
 *   - Variables with filters:              "name | upper"
 *   - Literal strings:                     "'Hello'"
 *   - Concatenation:                       "'Total: ' + price | currency('$')"
 *   - Mixed:                               "'Hi ' + name | upper + '!'"
 *   - Bracket notation:                    "[].name | trim"
 *   - Escape sequences in literals:        "'Line1\\nLine2'"
 *
 * Operator precedence:
 *   - | (filter) binds tighter than + (concatenation)
 *   - So "a | upper + b" = (a | upper) + b
 */
class ExpressionParser
{
    /** @var list<array{type: string, value: string, filters: list<array{name: string, params: list<mixed>}>, localOnly?: bool}> */
    public array $terms;

    /**
     * Whether this expression contains concatenation (+).
     */
    public bool $hasConcatenation;

    /**
     * Whether this expression contains any filters (|).
     */
    public bool $hasFilters;

    public function __construct(array $terms, bool $hasConcatenation = false, bool $hasFilters = false)
    {
        $this->terms = $terms;
        $this->hasConcatenation = $hasConcatenation;
        $this->hasFilters = $hasFilters;
    }

    /**
     * Get the variable key for simple single-variable expressions (backward compat).
     * Returns null for concatenated expressions.
     */
    public function getVariableKey(): ?string
    {
        if (count($this->terms) !== 1 || $this->terms[0]['type'] !== 'variable') {
            return null;
        }

        return $this->terms[0]['value'];
    }

    /**
     * Get the filters for simple single-variable expressions (backward compat).
     * Returns empty array for concatenated expressions.
     *
     * @return list<array{name: string, params: list<mixed>}>
     */
    public function getFilters(): array
    {
        if (count($this->terms) !== 1 || $this->terms[0]['type'] !== 'variable') {
            return [];
        }

        return $this->terms[0]['filters'];
    }

    /**
     * Parse an expression string into its components.
     */
    public static function parse(string $expression): self
    {
        $expression = trim($expression);

        // Check for concatenation
        $hasConcatenation = self::hasConcatenation($expression);
        $hasFilters = self::hasFilters($expression);

        if ($hasConcatenation) {
            $segments = self::splitByPlus($expression);
        } else {
            $segments = [$expression];
        }

        $terms = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $terms[] = self::parseTerm($segment);
        }

        return new self($terms, $hasConcatenation, $hasFilters);
    }

    /**
     * Check if an expression contains pipe filters (|) outside quotes.
     */
    public static function hasFilters(string $expression): bool
    {
        return self::containsOutsideQuotes($expression, '|');
    }

    /**
     * Check if an expression contains concatenation (+) outside quotes.
     */
    public static function hasConcatenation(string $expression): bool
    {
        return self::containsOutsideQuotes($expression, '+');
    }

    /**
     * Check if an expression contains filters, concatenation, or literals.
     * Used to determine whether to use expression parsing vs legacy variable resolution.
     */
    public static function isExpression(string $expression): bool
    {
        return self::hasFilters($expression)
            || self::hasConcatenation($expression)
            || self::isQuotedString($expression);
    }

    // ── Internal parsing ───────────────────────────

    /**
     * Parse a single term (between + operators).
     * Can be a literal string or a variable-with-filters.
     */
    private static function parseTerm(string $segment): array
    {
        $segment = trim($segment);

        // Check if it's a literal string
        if (self::isQuotedString($segment)) {
            return [
                'type' => 'literal',
                'value' => self::unesquote(substr($segment, 1, -1)),
                'filters' => [],
            ];
        }

        // It's a variable, possibly with filters
        $parts = self::splitByPipe($segment);
        $variableKey = trim(array_shift($parts));
        $filters = [];

        foreach ($parts as $filterSegment) {
            $filterSegment = trim($filterSegment);

            if (preg_match('/^(\w+)\s*\((.+)\)$/s', $filterSegment, $matches)) {
                $filterName = $matches[1];
                $params = self::parseParams($matches[2]);
            } elseif (preg_match('/^(\w+)$/', $filterSegment)) {
                $filterName = $filterSegment;
                $params = [];
            } else {
                continue;
            }

            $filters[] = ['name' => $filterName, 'params' => $params];
        }

        return [
            'type' => 'variable',
            'value' => $variableKey,
            'filters' => $filters,
            'localOnly' => str_starts_with($variableKey, '[].') || str_contains($variableKey, '[].'),
        ];
    }

    /**
     * Check if a string starts and ends with matching quotes.
     */
    private static function isQuotedString(string $str): bool
    {
        $str = trim($str);
        if (strlen($str) < 2) {
            return false;
        }

        $first = $str[0];
        $last = $str[strlen($str) - 1];

        return ($first === "'" && $last === "'") || ($first === '"' && $last === '"');
    }

    /**
     * Process escape sequences in a string literal.
     *
     * Supports: \n → newline, \t → tab, \\ → backslash, \' → single quote, \" → double quote
     */
    private static function unesquote(string $str): string
    {
        $replacements = [
            '\\n' => "\n",
            '\\t' => "\t",
            '\\\\' => '\\',
            "\\'" => "'",
            '\\"' => '"',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $str);
    }

    /**
     * Split by + outside quotes and parentheses.
     *
     * @return list<string>
     */
    private static function splitByPlus(string $expression): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;

        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
            } elseif ($char === '(' && !$inSingleQuote && !$inDoubleQuote) {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' && !$inSingleQuote && !$inDoubleQuote) {
                $depth--;
                $current .= $char;
            } elseif ($char === '+' && !$inSingleQuote && !$inDoubleQuote && $depth === 0) {
                $segments[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * Split by pipe | outside quotes.
     *
     * @return list<string>
     */
    private static function splitByPipe(string $expression): array
    {
        $segments = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;

        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
            } elseif ($char === '|' && !$inSingleQuote && !$inDoubleQuote) {
                $segments[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * Parse comma-separated parameters, respecting quoted strings.
     *
     * @return list<mixed>
     */
    private static function parseParams(string $paramsString): array
    {
        $paramsString = trim($paramsString);

        if ($paramsString === '') {
            return [];
        }

        $params = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;

        for ($i = 0; $i < strlen($paramsString); $i++) {
            $char = $paramsString[$i];

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
            } elseif ($char === ',' && !$inSingleQuote && !$inDoubleQuote) {
                $params[] = self::coerceParam(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $params[] = self::coerceParam(trim($current));

        return $params;
    }

    /**
     * Coerce a raw parameter string into a typed value.
     */
    private static function coerceParam(string $raw): mixed
    {
        // Double-quoted string
        if (preg_match('/^"(.*)"$/s', $raw, $m)) {
            return $m[1];
        }

        // Single-quoted string
        if (preg_match("/^'(.*)'$/s", $raw, $m)) {
            return $m[1];
        }

        // Boolean
        if (strtolower($raw) === 'true') {
            return true;
        }

        if (strtolower($raw) === 'false') {
            return false;
        }

        // Integer
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }

        // Float
        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return (float) $raw;
        }

        return $raw;
    }

    /**
     * Check if a character appears outside quotes in a string.
     */
    private static function containsOutsideQuotes(string $expression, string $char): bool
    {
        $inSingle = false;
        $inDouble = false;

        for ($i = 0; $i < strlen($expression); $i++) {
            $c = $expression[$i];

            if ($c === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($c === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($c === $char && !$inSingle && !$inDouble) {
                return true;
            }
        }

        return false;
    }
}
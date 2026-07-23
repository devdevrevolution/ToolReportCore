<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Public facade for the expression evaluation pipeline.
 *
 * Combines Tokenizer, Parser, and Evaluator into a single entry point.
 * Provides a fast path for simple variable expressions.
 */
class ExpressionEvaluator
{
    private Tokenizer $tokenizer;
    private Parser $parser;
    private Evaluator $evaluator;

    public function __construct(?FunctionRegistry $registry = null)
    {
        $this->tokenizer = new Tokenizer();
        $this->parser = new Parser();
        $this->evaluator = new Evaluator($registry ?? FunctionRegistry::defaults());
    }

    /**
     * Evaluate an expression string and return the result.
     *
     * @param callable $resolve fn(string $key): mixed — variable resolution
     */
    public function evaluateExpression(string $expression, callable $resolve): string
    {
        $trimmed = trim($expression);

        // Fast path: simple variable reference (word chars, dots, brackets only)
        if (self::isFastPath($trimmed)) {
            // If it's a pure number, return it directly
            if (is_numeric($trimmed)) {
                return $trimmed;
            }

            $value = $resolve($trimmed);

            return $value !== null ? $this->evaluator->evaluate(new \Toolreport\Core\Expression\Ast\VariableNode($trimmed), $resolve) : '';
        }

        $tokens = $this->tokenizer->tokenize($trimmed);
        $ast = $this->parser->parse($tokens);
        $result = $this->evaluator->evaluate($ast, $resolve);

        return $result;
    }

    /**
     * Check if an expression is a simple variable reference (fast path).
     */
    public static function isFastPath(string $expression): bool
    {
        return preg_match('/^[\w.\[\]]+$/', $expression) === 1;
    }
}

<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

use Toolreport\Core\Expression\Ast\ArrayLiteralNode;
use Toolreport\Core\Expression\Ast\BinaryOpNode;
use Toolreport\Core\Expression\Ast\ExpressionNode;
use Toolreport\Core\Expression\Ast\FilterChainNode;
use Toolreport\Core\Expression\Ast\FunctionCallNode;
use Toolreport\Core\Expression\Ast\NumberLiteralNode;
use Toolreport\Core\Expression\Ast\StringLiteralNode;
use Toolreport\Core\Expression\Ast\VariableNode;

/**
 * Visitor-pattern evaluator for the expression AST.
 *
 * Walks the AST tree and produces a result by dispatching to visit methods.
 * Receives a resolve callback for variable resolution.
 */
class Evaluator
{
    private FunctionRegistry $functions;
    private ?\Closure $resolve = null;

    public function __construct(FunctionRegistry $functions)
    {
        $this->functions = $functions;
    }

    /**
     * Evaluate an AST node with the given resolve callback.
     *
     * @param callable $resolve fn(string $key): mixed
     */
    public function evaluate(ExpressionNode $node, callable $resolve): string
    {
        $this->resolve = \Closure::fromCallable($resolve);
        $result = $node->accept($this);

        return $this->stringify($result);
    }

    public function visitFunctionCall(FunctionCallNode $node): mixed
    {
        $fn = $this->functions->get($node->name);

        // Resolve all argument nodes
        $resolvedArgs = array_map(
            fn (ExpressionNode $arg) => $arg->accept($this),
            $node->args,
        );

        // First arg is $value (the "primary"), rest are params
        $value = $resolvedArgs[0] ?? null;
        $params = array_slice($resolvedArgs, 1);

        return $fn->apply($value, $params, $this->resolve);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        return ($this->resolve)($node->path);
    }

    public function visitNumberLiteral(NumberLiteralNode $node): mixed
    {
        return $node->value;
    }

    public function visitStringLiteral(StringLiteralNode $node): mixed
    {
        return $node->value;
    }

    public function visitArrayLiteral(ArrayLiteralNode $node): mixed
    {
        return $node->elements;
    }

    public function visitBinaryOp(BinaryOpNode $node): mixed
    {
        $left = $this->stringify($node->left->accept($this));
        $right = $this->stringify($node->right->accept($this));

        return match ($node->operator) {
            '+' => $left . $right,
            default => throw new \InvalidArgumentException("Unknown operator: {$node->operator}"),
        };
    }

    public function visitFilterChain(FilterChainNode $node): mixed
    {
        $value = $node->base->accept($this);

        foreach ($node->filters as $filter) {
            $fn = $this->functions->get($filter->name);
            $resolvedParams = array_map(
                fn (ExpressionNode $p) => $p->accept($this),
                $filter->params,
            );
            $value = $fn->apply($value, $resolvedParams, $this->resolve);
        }

        return $value;
    }

    /**
     * Convert a value to its string representation.
     */
    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}

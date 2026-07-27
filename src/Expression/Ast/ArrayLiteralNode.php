<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing an array literal: [1, 2, 3] or ["a", "b"]
 */
class ArrayLiteralNode extends ExpressionNode
{
    /**
     * @param list<mixed> $elements Parsed array elements
     */
    public function __construct(
        public array $elements,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitArrayLiteral($this);
    }
}

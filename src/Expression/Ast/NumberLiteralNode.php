<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a numeric literal.
 */
class NumberLiteralNode extends ExpressionNode
{
    public function __construct(
        public float $value,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitNumberLiteral($this);
    }
}

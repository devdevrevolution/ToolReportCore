<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a string literal.
 */
class StringLiteralNode extends ExpressionNode
{
    public function __construct(
        public string $value,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitStringLiteral($this);
    }
}

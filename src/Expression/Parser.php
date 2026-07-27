<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

use Toolreport\Core\Expression\Ast\ArrayLiteralNode;
use Toolreport\Core\Expression\Ast\BinaryOpNode;
use Toolreport\Core\Expression\Ast\ExpressionNode;
use Toolreport\Core\Expression\Ast\FilterApplication;
use Toolreport\Core\Expression\Ast\FilterChainNode;
use Toolreport\Core\Expression\Ast\FunctionCallNode;
use Toolreport\Core\Expression\Ast\NumberLiteralNode;
use Toolreport\Core\Expression\Ast\StringLiteralNode;
use Toolreport\Core\Expression\Ast\VariableNode;

/**
 * Recursive descent parser for the expression language.
 *
 * Grammar (EBNF):
 *   expression     = primary ( ( '|' functionCall ) | ( '+' primary ) )*
 *   primary        = functionCall | variable | numberLiteral | stringLiteral | '(' expression ')'
 *   functionCall   = IDENTIFIER '(' argumentList? ')'
 *   argumentList   = expression ( ',' expression )*
 *   variable       = IDENTIFIER
 *   numberLiteral  = NUMBER
 *   stringLiteral  = STRING
 *
 * Precedence:
 *   - | (pipe) binds tighter than + (concatenation)
 *   - Function calls bind tighter than both
 */
class Parser
{
    /** @var list<Token> */
    private array $tokens;
    private int $current = 0;

    /**
     * Parse a token list into an AST.
     *
     * @throws \InvalidArgumentException on syntax errors
     */
    public function parse(array $tokens): ExpressionNode
    {
        $this->tokens = $tokens;
        $this->current = 0;

        $node = $this->parseExpression();

        if ($this->peek()->type !== TokenType::EOF) {
            throw new \InvalidArgumentException(
                sprintf('Unexpected token "%s" at position %d', $this->peek()->value, $this->peek()->position)
            );
        }

        return $node;
    }

    /**
     * Parse an expression: concatenation (pipe binds tighter than +)
     *
     * Grammar:
     *   expression = concatenation
     *   concatenation = pipe ( '+' pipe )*
     *   pipe = primary ( '|' filterApplication )*
     */
    private function parseExpression(): ExpressionNode
    {
        // Parse the first pipe expression (| binds tighter than +)
        $left = $this->parsePipeExpression();

        // Handle concatenation: left + right
        while ($this->peek()->type === TokenType::OPERATOR && $this->peek()->value === '+') {
            $this->advance(); // consume '+'
            $right = $this->parsePipeExpression();
            $left = new BinaryOpNode($left, '+', $right);
        }

        return $left;
    }

    /**
     * Parse a pipe expression: primary ('|' filterApplication)*
     *
     * Pipe binds tighter than concatenation.
     */
    private function parsePipeExpression(): ExpressionNode
    {
        $node = $this->parsePrimary();

        while ($this->peek()->type === TokenType::OPERATOR && $this->peek()->value === '|') {
            $this->advance(); // consume '|'

            $filters = [$this->parseFilterApplication()];

            // Collect chained filters: name | f1 | f2
            while ($this->peek()->type === TokenType::OPERATOR && $this->peek()->value === '|') {
                $this->advance(); // consume '|'
                $filters[] = $this->parseFilterApplication();
            }

            $node = new FilterChainNode($node, $filters);
        }

        return $node;
    }

    /**
     * Parse a filter application: IDENTIFIER ('(' argumentList? ')')?
     */
    private function parseFilterApplication(): FilterApplication
    {
        $nameToken = $this->expect(TokenType::FUNCTION, TokenType::VARIABLE);
        $name = $nameToken->value;
        $params = [];

        if ($this->peek()->type === TokenType::LPAREN) {
            $this->advance(); // consume '('
            $params = $this->parseArgumentList();
            $this->expect(TokenType::RPAREN);
        }

        return new FilterApplication($name, $params);
    }

    /**
     * Parse a primary expression.
     */
    private function parsePrimary(): ExpressionNode
    {
        // Parenthesized expression
        if ($this->peek()->type === TokenType::LPAREN) {
            $this->advance(); // consume '('
            $node = $this->parseExpression();
            $this->expect(TokenType::RPAREN);

            return $node;
        }

        // Number literal
        if ($this->peek()->type === TokenType::NUMBER) {
            $token = $this->advance();

            return new NumberLiteralNode((float) $token->value);
        }

        // String literal
        if ($this->peek()->type === TokenType::STRING) {
            $token = $this->advance();

            return new StringLiteralNode($token->value);
        }

        // Array literal: [ element, element, ... ]
        if ($this->peek()->type === TokenType::ARRAY) {
            $token = $this->advance();
            $elements = json_decode($token->value, true);

            return new ArrayLiteralNode($elements ?? []);
        }

        // Function call: IDENTIFIER '(' argumentList? ')'
        if ($this->peek()->type === TokenType::FUNCTION) {
            $name = $this->advance()->value;
            $this->expect(TokenType::LPAREN);
            $args = $this->parseArgumentList();
            $this->expect(TokenType::RPAREN);

            return new FunctionCallNode($name, $args);
        }

        // Variable
        if ($this->peek()->type === TokenType::VARIABLE) {
            $token = $this->advance();

            return new VariableNode($token->value);
        }

        throw new \InvalidArgumentException(
            sprintf('Unexpected token "%s" at position %d', $this->peek()->value, $this->peek()->position)
        );
    }

    /**
     * Parse a comma-separated argument list.
     *
     * @return list<ExpressionNode>
     */
    private function parseArgumentList(): array
    {
        $args = [];

        if ($this->peek()->type === TokenType::RPAREN) {
            return $args; // empty argument list
        }

        $args[] = $this->parseExpression();

        while ($this->peek()->type === TokenType::COMMA) {
            $this->advance(); // consume ','
            $args[] = $this->parseExpression();
        }

        return $args;
    }

    /**
     * Advance and return the current token.
     */
    private function advance(): Token
    {
        $token = $this->tokens[$this->current];

        if ($this->current < count($this->tokens) - 1) {
            $this->current++;
        }

        return $token;
    }

    /**
     * Peek at the current token without advancing.
     */
    private function peek(): Token
    {
        return $this->tokens[$this->current];
    }

    /**
     * Expect the current token to be of a specific type, then advance.
     *
     * @param TokenType ...$types
     */
    private function expect(TokenType ...$types): Token
    {
        $current = $this->peek();

        if (!in_array($current->type, $types, true)) {
            $expected = implode(' or ', array_map(fn (TokenType $t) => $t->value, $types));

            throw new \InvalidArgumentException(
                sprintf(
                    'Expected %s, got "%s" at position %d',
                    $expected,
                    $current->value ?: $current->type->value,
                    $current->position
                )
            );
        }

        return $this->advance();
    }
}

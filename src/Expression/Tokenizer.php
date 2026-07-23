<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * State machine tokenizer for the expression language.
 *
 * Converts an expression string into a list of tokens.
 * Uses one-token lookahead to distinguish FUNCTION from VARIABLE.
 */
class Tokenizer
{
    /**
     * Tokenize an expression string into a list of tokens.
     *
     * @throws \InvalidArgumentException on unterminated strings
     *
     * @return list<Token>
     */
    public function tokenize(string $input): array
    {
        $tokens = [];
        $length = strlen($input);
        $pos = 0;

        while ($pos < $length) {
            // Skip whitespace
            if ($input[$pos] === ' ' || $input[$pos] === "\t" || $input[$pos] === "\n" || $input[$pos] === "\r") {
                $pos++;
                continue;
            }

            $char = $input[$pos];

            // Single-character tokens
            if ($char === '(') {
                $tokens[] = new Token(TokenType::LPAREN, '(', $pos);
                $pos++;
                continue;
            }

            if ($char === ')') {
                $tokens[] = new Token(TokenType::RPAREN, ')', $pos);
                $pos++;
                continue;
            }

            if ($char === ',') {
                $tokens[] = new Token(TokenType::COMMA, ',', $pos);
                $pos++;
                continue;
            }

            // Operators: | or +
            if ($char === '|' || $char === '+') {
                $tokens[] = new Token(TokenType::OPERATOR, $char, $pos);
                $pos++;
                continue;
            }

            // Number literal
            if (ctype_digit($char) || ($char === '.' && $pos + 1 < $length && ctype_digit($input[$pos + 1]))) {
                $startPos = $pos;
                $number = '';

                while ($pos < $length && (ctype_digit($input[$pos]) || $input[$pos] === '.')) {
                    $number .= $input[$pos];
                    $pos++;
                }

                $tokens[] = new Token(TokenType::NUMBER, $number, $startPos);
                continue;
            }

            // String literal (single or double quoted)
            if ($char === "'" || $char === '"') {
                $tokens[] = $this->tokenizeString($input, $pos);
                continue;
            }

            // Identifier (variable or function) — starts with letter, underscore, or '[' for bracket notation
            if (preg_match('/[a-zA-Z_\[]/', $char)) {
                $startPos = $pos;
                $identifier = '';

                while ($pos < $length && preg_match('/[\w.\[\]]/', $input[$pos])) {
                    $identifier .= $input[$pos];
                    $pos++;
                }

                // Lookahead: is the next non-whitespace char '('? → FUNCTION
                $lookaheadPos = $pos;
                while ($lookaheadPos < $length && ($input[$lookaheadPos] === ' ' || $input[$lookaheadPos] === "\t")) {
                    $lookaheadPos++;
                }

                if ($lookaheadPos < $length && $input[$lookaheadPos] === '(') {
                    $tokens[] = new Token(TokenType::FUNCTION, $identifier, $startPos);
                } else {
                    $tokens[] = new Token(TokenType::VARIABLE, $identifier, $startPos);
                }

                continue;
            }

            // Unknown character — skip it
            $pos++;
        }

        $tokens[] = new Token(TokenType::EOF, '', $length);

        return $tokens;
    }

    /**
     * Tokenize a quoted string literal.
     *
     * @throws \InvalidArgumentException on unterminated string
     */
    private function tokenizeString(string $input, int &$pos): Token
    {
        $quote = $input[$pos];
        $startPos = $pos;
        $pos++; // skip opening quote
        $value = '';
        $length = strlen($input);

        while ($pos < $length && $input[$pos] !== $quote) {
            $value .= $input[$pos];
            $pos++;
        }

        if ($pos >= $length) {
            throw new \InvalidArgumentException(
                sprintf('Unterminated string starting at position %d', $startPos)
            );
        }

        $pos++; // skip closing quote

        return new Token(TokenType::STRING, $value, $startPos);
    }
}

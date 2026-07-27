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

            // Check for bracket notation: [].something or items[].something
            // This should be handled as part of identifier, not as array literal
            if ($char === '[' && $this->isBracketNotation($input, $pos)) {
                // Will be handled by the identifier handler below
                // Fall through to identifier handling
            } elseif ($char === '[') {
                // Array literal
                $tokens[] = $this->tokenizeArrayLiteral($input, $pos);
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

            // Identifier (variable or function) — starts with letter, underscore, or bracket notation
            if (preg_match('/[a-zA-Z_\[]/', $char)) {
                $startPos = $pos;
                $identifier = '';

                // Handle bracket notation at start: [].name
                if ($char === '[') {
                    $identifier = $this->consumeBracketNotation($input, $pos);
                }

                // Consume word characters and dots (main loop)
                while ($pos < $length) {
                    // Consume word characters and dots
                    while ($pos < $length && preg_match('/[\w.]/', $input[$pos])) {
                        $identifier .= $input[$pos];
                        $pos++;
                    }

                    // Check for bracket notation: identifier[] or identifier[index]
                    if ($pos < $length && $input[$pos] === '[') {
                        if ($this->isBracketNotation($input, $pos)) {
                            $identifier .= $this->consumeBracketNotation($input, $pos);
                        } else {
                            // Array literal, stop consuming identifier
                            break;
                        }
                    } else {
                        // No more bracket notation, done
                        break;
                    }
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

    /**
     * Consume bracket notation and return the string representation.
     *
     * Handles: [], [0], [].name, etc.
     */
    private function consumeBracketNotation(string $input, int &$pos): string
    {
        $length = strlen($input);
        $result = '[';
        $pos++; // skip [

        // Skip whitespace
        while ($pos < $length && $input[$pos] === ' ') {
            $pos++;
        }

        // Check for empty brackets
        if ($pos < $length && $input[$pos] === ']') {
            $result .= ']';
            $pos++; // skip ]
            return $result;
        }

        // Check for index (number)
        if ($pos < $length && ctype_digit($input[$pos])) {
            while ($pos < $length && ctype_digit($input[$pos])) {
                $result .= $input[$pos];
                $pos++;
            }
        }

        // Skip whitespace
        while ($pos < $length && $input[$pos] === ' ') {
            $pos++;
        }

        // Expect closing bracket
        if ($pos < $length && $input[$pos] === ']') {
            $result .= ']';
            $pos++; // skip ]
        }

        return $result;
    }

    /**
     * Check if brackets at position are bracket notation (not array literal).
     *
     * Bracket notation: [].name, items[].total, [0].name
     * Array literal: [1, 2, 3], ["a", "b"]
     */
    private function isBracketNotation(string $input, int $pos): bool
    {
        $length = strlen($input);
        $pos++; // skip [

        // Skip whitespace
        while ($pos < $length && $input[$pos] === ' ') {
            $pos++;
        }

        // Empty brackets: [] → bracket notation if followed by . or end of expression
        if ($pos < $length && $input[$pos] === ']') {
            $pos++; // skip ]

            // Skip whitespace
            while ($pos < $length && $input[$pos] === ' ') {
                $pos++;
            }

            // If followed by . or end of expression, it's bracket notation
            if ($pos >= $length || $input[$pos] === '.') {
                return true;
            }

            // If followed by ( or , or ), it's an empty array (like SUM([]))
            return false;
        }

        // Non-empty brackets: check if it looks like array literal (numbers/strings)
        // If first character after [ is a digit, quote, or [, it's an array literal
        if ($pos < $length && (ctype_digit($input[$pos]) || $input[$pos] === "'" || $input[$pos] === '"' || $input[$pos] === '[')) {
            return false;
        }

        // Otherwise, it might be bracket notation with index (like [0].name)
        // Let the identifier handler deal with it
        return true;
    }

    /**
     * Tokenize an array literal: [ element, element, ... ]
     *
     * Returns a single ARRAY token with the parsed values.
     */
    private function tokenizeArrayLiteral(string $input, int &$pos): Token
    {
        $startPos = $pos;
        $pos++; // skip opening [
        $length = strlen($input);
        $elements = [];

        // Skip whitespace
        while ($pos < $length && $input[$pos] === ' ') {
            $pos++;
        }

        // Check for empty array
        if ($pos < $length && $input[$pos] === ']') {
            $pos++; // skip ]
            return new Token(TokenType::ARRAY, '[]', $startPos);
        }

        // Parse elements
        while ($pos < $length && $input[$pos] !== ']') {
            // Skip whitespace
            while ($pos < $length && $input[$pos] === ' ') {
                $pos++;
            }

            // Parse element (number or string)
            if (ctype_digit($input[$pos]) || ($input[$pos] === '.' && $pos + 1 < $length && ctype_digit($input[$pos + 1]))) {
                $number = '';
                while ($pos < $length && (ctype_digit($input[$pos]) || $input[$pos] === '.')) {
                    $number .= $input[$pos];
                    $pos++;
                }
                $elements[] = $number;
            } elseif ($input[$pos] === "'" || $input[$pos] === '"') {
                $stringToken = $this->tokenizeString($input, $pos);
                $elements[] = $stringToken->value;
            } else {
                throw new \InvalidArgumentException(
                    sprintf('Unexpected character "%s" in array literal at position %d', $input[$pos], $pos)
                );
            }

            // Skip whitespace
            while ($pos < $length && $input[$pos] === ' ') {
                $pos++;
            }

            // Check for comma or closing bracket
            if ($pos < $length && $input[$pos] === ',') {
                $pos++; // skip comma
            } elseif ($pos < $length && $input[$pos] !== ']') {
                throw new \InvalidArgumentException(
                    sprintf('Expected "," or "]" in array literal at position %d', $pos)
                );
            }
        }

        if ($pos >= $length) {
            throw new \InvalidArgumentException(
                sprintf('Unterminated array literal starting at position %d', $startPos)
            );
        }

        $pos++; // skip ]

        // Store the array as a JSON string for easy parsing
        return new Token(TokenType::ARRAY, json_encode($elements), $startPos);
    }
}

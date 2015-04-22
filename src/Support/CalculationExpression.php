<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use InvalidArgumentException;

/**
 * Arithmetic-only grammar: decimal literals, field keys, parentheses, unary +/- and binary + - * /.
 */
final class CalculationExpression
{
    public const int MAX_LENGTH = 4096;

    public function validate(string $expression): void
    {
        $this->parse($expression);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function evaluate(string $expression, array $values): float|int
    {
        $stack = [];

        foreach ($this->parse($expression) as $token) {
            if (is_float($token)) {
                $stack[] = $token;
            } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token) === 1) {
                $value = $values[$token] ?? 0;
                $stack[] = is_numeric($value) ? (float) $value : 0.0;
            } else {
                $right = array_pop($stack);

                if ($right === null) {
                    throw new InvalidArgumentException('Calculation requires an operand.');
                }

                if ($token === 'u+' || $token === 'u-') {
                    $stack[] = $token === 'u-' ? -$right : $right;

                    continue;
                }

                $left = array_pop($stack);

                if ($left === null) {
                    throw new InvalidArgumentException('Calculation requires two operands.');
                }

                $stack[] = match ($token) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right === 0.0 ? 0.0 : $left / $right,
                    default => throw new InvalidArgumentException('Unsupported calculation operator.'),
                };
            }
        }

        if (count($stack) !== 1 || ! is_finite($stack[0])) {
            throw new InvalidArgumentException('Calculation must produce one finite result.');
        }

        $result = $stack[0];

        return floor($result) === $result && $result >= PHP_INT_MIN && $result < PHP_INT_MAX
            ? (int) $result
            : $result;
    }

    /**
     * @return list<float|string>
     */
    private function parse(string $expression): array
    {
        if (strlen($expression) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Calculation expression is too long.');
        }

        // Keep unsupported characters as tokens so invalid syntax cannot be silently discarded.
        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*|(?:\d+(?:\.\d*)?|\.\d+)|\S/', $expression, $matches);
        $output = [];
        $operators = [];
        $expectsOperand = true;

        foreach ($matches[0] as $token) {
            if (is_numeric($token) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token) === 1) {
                if (! $expectsOperand) {
                    throw new InvalidArgumentException('Calculation requires an operator.');
                }

                $output[] = is_numeric($token) ? (float) $token : $token;
                $expectsOperand = false;

                continue;
            }

            if ($token === '(' && $expectsOperand) {
                $operators[] = $token;

                continue;
            }

            if ($token === ')' && ! $expectsOperand) {
                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if (array_pop($operators) !== '(') {
                    throw new InvalidArgumentException('Calculation parentheses must be balanced.');
                }

                continue;
            }

            if (! in_array($token, ['+', '-', '*', '/'], true)) {
                throw new InvalidArgumentException('Unsupported calculation syntax.');
            }

            if ($expectsOperand) {
                if ($token !== '+' && $token !== '-') {
                    throw new InvalidArgumentException('Calculation requires an operand.');
                }

                $operators[] = 'u' . $token;

                continue;
            }

            while ($operators !== [] && end($operators) !== '(' && $this->precedence(end($operators)) >= $this->precedence($token)) {
                $output[] = array_pop($operators);
            }

            $operators[] = $token;
            $expectsOperand = true;
        }

        if ($expectsOperand) {
            throw new InvalidArgumentException('Calculation requires an operand.');
        }

        while ($operators !== []) {
            $operator = array_pop($operators);

            if ($operator === '(') {
                throw new InvalidArgumentException('Calculation parentheses must be balanced.');
            }

            $output[] = $operator;
        }

        return $output;
    }

    private function precedence(string $operator): int
    {
        return match ($operator) {
            'u+', 'u-' => 3,
            '*', '/' => 2,
            default => 1,
        };
    }
}

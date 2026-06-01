<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static array<string, mixed> run(Form $form, array<string, mixed> $input = [])
 */
final class CalculateFormFieldValuesAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(Form $form, array $input = []): array
    {
        $values = $input;

        foreach (ResolveVisibleFormFieldsAction::run($form, $values) as $field) {
            if ($field->type !== FormFieldType::Calculation) {
                continue;
            }

            if ($field->calculationExpression === null) {
                continue;
            }

            $values[$field->key] = $this->evaluate($field, $values);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function evaluate(FormFieldData $field, array $values): float|int
    {
        $tokens = $this->tokens($field->calculationExpression ?? '');
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if ($this->isNumber($token)) {
                $output[] = (float) $token;

                continue;
            }

            if ($this->isFieldKey($token)) {
                $output[] = $this->numericValue($values[$token] ?? 0);

                continue;
            }

            if ($token === '(') {
                $operators[] = $token;

                continue;
            }

            if ($token === ')') {
                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if (end($operators) === '(') {
                    array_pop($operators);
                }

                continue;
            }

            while (
                $operators !== []
                && end($operators) !== '('
                && $this->precedence(end($operators)) >= $this->precedence($token)
            ) {
                $output[] = array_pop($operators);
            }

            $operators[] = $token;
        }

        while ($operators !== []) {
            $operator = array_pop($operators);

            if ($operator !== '(') {
                $output[] = $operator;
            }
        }

        $result = $this->evaluateReversePolish($output);

        return floor($result) === $result ? (int) $result : $result;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $expression): array
    {
        preg_match_all('/[a-zA-Z_]\w*|\d+(?:\.\d+)?|[()+\-*\/]/', $expression, $matches);

        return $matches[0];
    }

    private function isNumber(string $token): bool
    {
        return preg_match('/^\d+(?:\.\d+)?$/', $token) === 1;
    }

    private function isFieldKey(string $token): bool
    {
        return preg_match('/^[a-zA-Z_]\w*$/', $token) === 1;
    }

    private function numericValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function precedence(string $operator): int
    {
        return in_array($operator, ['*', '/'], true) ? 2 : 1;
    }

    /**
     * @param  array<int, float|string>  $tokens
     */
    private function evaluateReversePolish(array $tokens): float
    {
        $stack = [];

        foreach ($tokens as $token) {
            if (is_float($token)) {
                $stack[] = $token;

                continue;
            }

            $right = array_pop($stack) ?? 0.0;
            $left = array_pop($stack) ?? 0.0;

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right === 0.0 ? 0.0 : $left / $right,
                default => 0.0,
            };
        }

        return (float) ($stack[0] ?? 0.0);
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldConditionData;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldConditionOperator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static bool run(FormFieldData $field, array<string, mixed> $input)
 */
final class EvaluateFormFieldVisibilityAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(FormFieldData $field, array $input): bool
    {
        foreach ($field->visibilityConditions() as $condition) {
            if (! $this->conditionMatches($condition, $input)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function conditionMatches(FormFieldConditionData $condition, array $input): bool
    {
        $actualValue = $input[$condition->fieldKey] ?? null;

        return match ($condition->operator) {
            FormFieldConditionOperator::Equals => $this->valuesAreEquivalent($actualValue, $condition->value),
            FormFieldConditionOperator::NotEquals => ! $this->valuesAreEquivalent($actualValue, $condition->value),
            FormFieldConditionOperator::Filled => filled($actualValue),
            FormFieldConditionOperator::Blank => blank($actualValue),
            FormFieldConditionOperator::Contains => $this->containsValue($actualValue, $condition->value),
            FormFieldConditionOperator::GreaterThan => $this->compareNumericValues($actualValue, $condition->value) > 0,
            FormFieldConditionOperator::LessThan => $this->compareNumericValues($actualValue, $condition->value) < 0,
        };
    }

    private function valuesAreEquivalent(mixed $actualValue, mixed $expectedValue): bool
    {
        if (is_bool($actualValue) || is_bool($expectedValue)) {
            return $this->booleanValue($actualValue) === $this->booleanValue($expectedValue);
        }

        if (is_numeric($actualValue) && is_numeric($expectedValue)) {
            return (float) $actualValue === (float) $expectedValue;
        }

        return (string) $actualValue === (string) $expectedValue;
    }

    private function containsValue(mixed $actualValue, mixed $expectedValue): bool
    {
        if (is_array($actualValue)) {
            foreach ($actualValue as $itemValue) {
                if ($this->valuesAreEquivalent($itemValue, $expectedValue)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_scalar($actualValue) || ! is_scalar($expectedValue)) {
            return false;
        }

        return str_contains((string) $actualValue, (string) $expectedValue);
    }

    private function compareNumericValues(mixed $actualValue, mixed $expectedValue): int
    {
        if (! is_numeric($actualValue) || ! is_numeric($expectedValue)) {
            return 0;
        }

        return (float) $actualValue <=> (float) $expectedValue;
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }
}

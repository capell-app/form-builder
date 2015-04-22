<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Rules;

use Capell\FormBuilder\Support\CalculationExpression;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class ValidCalculationExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('capell-form-builder::form.invalid_calculation_expression'));

            return;
        }

        try {
            (new CalculationExpression)->validate($value);
        } catch (InvalidArgumentException) {
            $fail(__('capell-form-builder::form.invalid_calculation_expression'));
        }
    }
}

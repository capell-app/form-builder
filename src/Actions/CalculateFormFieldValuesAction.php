<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Support\CalculationExpression;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, mixed> run(Form $form, array<string, mixed> $input = [])
 */
final class CalculateFormFieldValuesAction
{
    use AsFake;
    use AsObject;

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
        try {
            return (new CalculationExpression)->evaluate($field->calculationExpression ?? '', $values);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                $field->key => __('capell-form-builder::form.calculation_failed'),
            ]);
        }
    }
}

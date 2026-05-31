<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static Collection<int, FormStepData> run(Form $form, array<string, mixed> $input = [])
 */
final class BuildFormStepsAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, FormStepData>
     */
    public function handle(Form $form, array $input = []): Collection
    {
        return ResolveVisibleFormFieldsAction::run($form, $input)
            ->groupBy(fn (FormFieldData $field): string => $this->stepKey($field))
            ->map(fn (Collection $fields, string $stepKey): FormStepData => new FormStepData(
                key: $stepKey,
                label: $this->stepLabel($stepKey),
                fields: $fields->values(),
            ))
            ->values();
    }

    private function stepKey(FormFieldData $field): string
    {
        $stepKey = is_string($field->stepKey) ? Str::slug($field->stepKey) : '';

        return $stepKey !== '' ? $stepKey : 'default';
    }

    private function stepLabel(string $stepKey): string
    {
        if ($stepKey === 'default') {
            return (string) __('capell-form-builder::form.default_step');
        }

        return Str::of($stepKey)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }
}

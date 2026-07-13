<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static array<string, array<int, string>> run(Form $form, array<string, mixed> $input = [], ?Collection<int, FormFieldData> $fields = null)
 */
final class BuildFormComponentValidationRulesAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     * @param  Collection<int, FormFieldData>|null  $fields
     * @return array<string, array<int, string>>
     */
    public function handle(Form $form, array $input = [], ?Collection $fields = null): array
    {
        $fieldKeys = $fields?->pluck('key')->all();

        return collect(BuildFormValidationRulesAction::run($form, $input))
            ->filter(static fn (array $rules, string $fieldKey): bool => $fieldKeys === null || in_array($fieldKey, $fieldKeys, true))
            ->mapWithKeys(static fn (array $rules, string $fieldKey): array => ['data.' . $fieldKey => $rules])
            ->all();
    }
}

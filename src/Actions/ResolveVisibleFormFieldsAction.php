<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\LaravelData\DataCollection;

/**
 * @method static Collection<int, FormFieldData> run(Form $form, array<string, mixed> $input = [])
 */
final class ResolveVisibleFormFieldsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, FormFieldData>
     */
    public function handle(Form $form, array $input = []): Collection
    {
        return $this->fields($form)
            ->filter(
                fn (FormFieldData $field): bool => EvaluateFormFieldVisibilityAction::run($field, $input),
            )
            ->values();
    }

    /**
     * @return Collection<int, FormFieldData>
     */
    private function fields(Form $form): Collection
    {
        $schema = $form->schema;

        if ($schema instanceof DataCollection) {
            return new Collection($schema->items());
        }

        if (! is_array($schema)) {
            return collect();
        }

        return collect($schema)
            ->map(fn (mixed $field): ?FormFieldData => $this->fieldFromSchemaValue($field))
            ->filter(fn (?FormFieldData $field): bool => $field instanceof FormFieldData)
            ->values();
    }

    private function fieldFromSchemaValue(mixed $field): ?FormFieldData
    {
        if ($field instanceof FormFieldData) {
            return $field;
        }

        if (is_array($field)) {
            return FormFieldData::from($field);
        }

        return null;
    }
}

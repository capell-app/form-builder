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
        $fields = $this->fields($form);
        $hidden = [];

        // A field hidden by its own condition has no answer, whatever the browser still holds for it, so a
        // field that depends on it is judged without that stale value. Resolving that to a fixed point makes a
        // chain (A shows B, B shows C) give validation, storage and rendering the same answer.
        for ($pass = 0; $pass <= $fields->count(); $pass++) {
            $effective = array_diff_key($input, array_flip($hidden));
            $nowHidden = $fields
                ->reject(fn (FormFieldData $field): bool => EvaluateFormFieldVisibilityAction::run($field, $effective))
                ->map(static fn (FormFieldData $field): string => $field->key)
                ->values()
                ->all();

            if ($nowHidden === $hidden) {
                break;
            }

            $hidden = $nowHidden;
        }

        return $fields
            ->reject(static fn (FormFieldData $field): bool => in_array($field->key, $hidden, true))
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

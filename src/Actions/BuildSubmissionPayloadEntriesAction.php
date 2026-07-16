<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\LaravelData\DataCollection;

final class BuildSubmissionPayloadEntriesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return Collection<int, array{key: string, label: string, value: string}>
     */
    public function handle(Submission $submission): Collection
    {
        $values = $submission->payload->values ?? [];
        $labels = $this->fieldLabels($submission);

        return collect($values)
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'label' => $labels[$key] ?? str($key)->headline()->value(),
                'value' => $this->formatValue($value),
            ])
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function fieldLabels(Submission $submission): array
    {
        $form = $submission->getRelationValue('form');

        if (! $form instanceof Form) {
            return [];
        }

        $schema = $form->schema;

        if ($schema === null) {
            return [];
        }

        $fields = $schema instanceof DataCollection ? $schema->items() : $schema;

        $labels = [];

        foreach ($fields as $field) {
            if ($field instanceof FormFieldData) {
                $labels[$field->key] = $field->label;

                continue;
            }

            if (is_array($field) && is_string($field['key'] ?? null) && is_string($field['label'] ?? null)) {
                $labels[$field['key']] = $field['label'];
            }
        }

        return $labels;
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('capell-form-builder::generic.boolean.yes') : __('capell-form-builder::generic.boolean.no');
        }

        $storedFileReference = $this->storedFileReference($value);

        if ($storedFileReference !== null) {
            return (string) __('capell-form-builder::table.file_reference', [
                'name' => $storedFileReference['original_name'],
                'disk' => $storedFileReference['disk'],
                'path' => $storedFileReference['path'],
                'size' => number_format((int) $storedFileReference['size']),
            ]);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => $this->formatValue($item))
                ->implode(', ');
        }

        if ($value === null || $value === '') {
            return (string) __('capell-form-builder::generic.empty_value');
        }

        return (string) $value;
    }

    /**
     * @return array{original_name: string, disk: string, path: string, size: numeric}|null
     */
    private function storedFileReference(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! is_string($value['original_name'] ?? null)) {
            return null;
        }

        if (! is_string($value['disk'] ?? null)) {
            return null;
        }

        if (! is_string($value['path'] ?? null)) {
            return null;
        }

        if (! is_numeric($value['size'] ?? null)) {
            return null;
        }

        return [
            'original_name' => $value['original_name'],
            'disk' => $value['disk'],
            'path' => $value['path'],
            'size' => $value['size'],
        ];
    }
}

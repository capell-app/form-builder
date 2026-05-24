<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\LaravelData\DataCollection;

final class BuildSubmissionPayloadEntriesAction
{
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
        $schema = $submission->form?->schema;

        if ($schema === null) {
            return [];
        }

        $fields = $schema instanceof DataCollection ? $schema->items() : $schema;

        return collect($fields)
            ->mapWithKeys(function (mixed $field): array {
                if ($field instanceof FormFieldData) {
                    return [$field->key => $field->label];
                }

                if (is_array($field) && is_string($field['key'] ?? null) && is_string($field['label'] ?? null)) {
                    return [$field['key'] => $field['label']];
                }

                return [];
            })
            ->all();
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('capell-form-builder::generic.boolean.yes') : __('capell-form-builder::generic.boolean.no');
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => $this->formatValue($item))
                ->implode(', ');
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * @method static string run(?Form $form = null)
 */
final class BuildSubmissionsCsvAction
{
    use AsAction;

    /**
     * @return array<int, string>
     */
    private const array BASE_COLUMNS = [
        'submission_id',
        'form_id',
        'form_name',
        'site_id',
        'status',
        'submitted_at',
    ];

    public function handle(?Form $form = null): string
    {
        $query = Submission::query()
            ->with('form')
            ->oldest('submitted_at')
            ->orderBy('id');

        if ($form instanceof Form) {
            $query->where('form_id', $form->getKey());
        }

        $submissions = $query->get();

        $fieldKeys = $this->fieldKeys($submissions);
        $rows = [];
        $rows[] = [...self::BASE_COLUMNS, ...$fieldKeys];

        foreach ($submissions as $submission) {
            $values = $submission->payload->values ?? [];

            $rows[] = [
                $this->modelKey($submission),
                (string) $submission->form_id,
                $submission->form->name,
                (string) $submission->site_id,
                $submission->status->value,
                $submission->submitted_at->toIso8601String(),
                ...array_map(fn (string $fieldKey): string => $this->stringValue($values[$fieldKey] ?? null), $fieldKeys),
            ];
        }

        return $this->writeCsv($rows);
    }

    /**
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, string>
     */
    private function fieldKeys(Collection $submissions): array
    {
        return $submissions
            ->flatMap(function (Submission $submission): array {
                $schemaKeys = $this->schemaKeys($submission->form);
                $payloadKeys = array_keys($submission->payload->values ?? []);

                return [...$schemaKeys, ...$payloadKeys];
            })
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function schemaKeys(?Form $form): array
    {
        $schema = $form?->schema;

        if ($schema === null) {
            return [];
        }

        $fields = $schema instanceof DataCollection ? $schema->items() : $schema;

        return collect($fields)
            ->map(function (mixed $field): ?string {
                if ($field instanceof FormFieldData) {
                    return $field->key;
                }

                return is_array($field) && is_string($field['key'] ?? null) ? $field['key'] : null;
            })
            ->filter(fn (?string $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return is_string($json) ? $json : '';
    }

    private function modelKey(Model $model): string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? (string) $key : '';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, RuntimeException::class, 'Unable to open temporary CSV stream.');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);

        $csv = stream_get_contents($stream);
        fclose($stream);

        throw_if($csv === false, RuntimeException::class, 'Unable to read temporary CSV stream.');

        return $csv;
    }
}

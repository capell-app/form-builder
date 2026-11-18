<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * @method static string run(?Form $form = null, Builder<Submission>|null $scope = null)
 */
final class BuildSubmissionsCsvAction
{
    use AsFake;
    use AsObject;

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

    /**
     * @param  Builder<Submission>|null  $scope  Extra constraints, such as the actor's site access.
     */
    public function handle(?Form $form = null, ?Builder $scope = null): string
    {
        $stream = fopen('php://temp', 'r+');

        throw_if($stream === false, RuntimeException::class, 'Unable to open temporary CSV stream.');

        $this->writeTo($stream, $form, $scope);
        rewind($stream);

        $csv = stream_get_contents($stream);
        fclose($stream);

        throw_if($csv === false, RuntimeException::class, 'Unable to read temporary CSV stream.');

        return $csv;
    }

    /**
     * Streams rows to the given resource one submission at a time, so memory stays flat however many submissions exist.
     *
     * @param  resource  $stream
     * @param  Builder<Submission>|null  $scope
     */
    public function writeTo($stream, ?Form $form = null, ?Builder $scope = null): void
    {
        $query = ($scope ?? Submission::query())
            ->with('form')
            ->where('status', '!=', SubmissionStatus::Spam->value);

        if ($form instanceof Form) {
            $query->where('form_id', $form->getKey());
        }

        $fieldKeys = $this->fieldKeys($query->clone());

        $this->writeRow($stream, [...self::BASE_COLUMNS, ...$fieldKeys]);

        foreach ($query->lazyById() as $submission) {
            $values = $submission->payload->values ?? [];

            $this->writeRow($stream, [
                $this->modelKey($submission),
                (string) $submission->form_id,
                $submission->form->name,
                (string) $submission->site_id,
                $submission->status->value,
                $submission->submitted_at->toIso8601String(),
                ...array_map(fn (string $fieldKey): string => $this->stringValue($values[$fieldKey] ?? null), $fieldKeys),
            ]);
        }
    }

    private static function csvValue(string|int|float|bool|null $value): string|int|float|bool|null
    {
        // Encode only at the spreadsheet boundary; retain scalar numbers and storage values.
        return is_string($value) && preg_match('/^(?:[\x00-\x20]*[=+\-@]|[\x00-\x1f])/', $value) === 1
            ? "'" . $value
            : $value;
    }

    /**
     * @param  Builder<Submission>  $query
     * @return array<int, string>
     */
    private function fieldKeys(Builder $query): array
    {
        $keys = [];

        foreach ($query->lazyById() as $submission) {
            foreach ([...$this->schemaKeys($submission->form), ...array_keys($submission->payload->values ?? [])] as $key) {
                if (is_string($key) && $key !== '') {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
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

        // Stored upload references export as the file name only, never the storage disk or path.
        if (is_array($value) && is_string($value['original_name'] ?? null) && array_key_exists('path', $value)) {
            return $value['original_name'];
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
     * @param  resource  $stream
     * @param  array<int, string>  $row
     */
    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, array_map(self::csvValue(...), $row), escape: '');
    }
}

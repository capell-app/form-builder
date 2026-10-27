<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(array<array-key, array<string, mixed>> $schema, string $statePath = 'data.schema')
 */
final class ValidateFormFieldKeysAction
{
    use AsFake;
    use AsObject;

    /**
     * Validate raw repeater state before dehydration so errors retain row UUIDs.
     * Steps and conditional branches share the same answer-key namespace.
     *
     * @param  array<array-key, array<string, mixed>>  $schema
     */
    public function handle(array $schema, string $statePath = 'data.schema'): void
    {
        $fieldsByKey = [];

        foreach ($schema as $row => $field) {
            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $fieldsByKey['key:' . $key][$row] = is_string($field['label'] ?? null) && $field['label'] !== ''
                ? $field['label']
                : $key;
        }

        $errors = [];

        foreach ($fieldsByKey as $fields) {
            if (count($fields) < 2) {
                continue;
            }

            $message = __('capell-form-builder::form.admin.duplicate_field_key', [
                'fields' => implode(', ', $fields),
            ]);

            foreach (array_keys($fields) as $row) {
                $errors[$statePath . '.' . $row . '.key'] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

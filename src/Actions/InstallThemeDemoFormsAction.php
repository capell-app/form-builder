<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Str;
use JsonException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class InstallThemeDemoFormsAction
{
    use AsFake;
    use AsObject;

    /**
     * @throws JsonException
     */
    public function handle(int|string $siteId, string $formsPayload): void
    {
        $forms = json_decode($formsPayload, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($forms)) {
            return;
        }

        foreach ($forms as $form) {
            if (! is_array($form)) {
                continue;
            }

            $handle = $this->stringValue($form, 'handle');
            $name = $this->stringValue($form, 'name');

            if ($handle === null || $name === null) {
                continue;
            }

            $fields = $form['fields'] ?? [];

            Form::query()->updateOrCreate(
                ['site_id' => $siteId, 'handle' => Str::slug($handle)],
                [
                    'name' => $name,
                    'description' => $this->stringValue($form, 'description'),
                    'schema' => $this->schema(is_array($fields) ? $fields : []),
                    'settings' => [
                        'success_message' => $this->stringValue($form, 'success_message'),
                        'store_submissions' => true,
                        'notification_email' => null,
                        'collect_ip_address' => true,
                        'collect_user_agent' => true,
                    ],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<array<string, mixed>>
     */
    private function schema(array $fields): array
    {
        $schema = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $this->stringValue($field, 'key') ?? $this->stringValue($field, 'name');
            $label = $this->stringValue($field, 'label');

            if ($key === null || $label === null) {
                continue;
            }

            $fieldType = FormFieldType::tryFrom($this->stringValue($field, 'type') ?? '')
                ?? FormFieldType::Text;

            $schema[] = [
                'key' => Str::snake($key),
                'label' => $label,
                'type' => $fieldType->value,
                'required' => (bool) ($field['required'] ?? false),
                'placeholder' => $this->stringValue($field, 'placeholder'),
                'help_text' => $this->stringValue($field, 'help_text'),
                'options' => $this->options($field['options'] ?? []),
                'validation_rules' => $this->stringList($field['validation_rules'] ?? []),
            ];
        }

        return $schema;
    }

    /**
     * @return array<string, string>
     */
    private function options(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $normalized[is_string($key) ? $key : $value] = $value;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function stringValue(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}

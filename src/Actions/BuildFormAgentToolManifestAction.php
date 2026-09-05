<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Enums\FormFieldType;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/** Build a public write tool from the already hydrated, visible form fields. */
final class BuildFormAgentToolManifestAction
{
    use AsObject;

    /**
     * @param  Collection<int, FormStepData>  $steps
     * @param  Collection<int, FormFieldData>  $allFields
     * @return array{
     *     capellAgentSchema: int,
     *     messages: array{confirmForm: string},
     *     tools: list<array<string, mixed>>
     * }|null
     */
    public function handle(Collection $steps, Collection $allFields, string $formId): ?array
    {
        $steps = $steps->values();

        if (
            $steps->count() !== 1
            || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,127}$/', $formId) !== 1
            || (bool) config('capell-form-builder.spam_protection.enabled', false)
        ) {
            return null;
        }

        $step = $steps->first();

        if (! $step instanceof FormStepData) {
            return null;
        }

        foreach ($allFields as $field) {
            if (! $field instanceof FormFieldData || $this->schemaFor($field) === null) {
                return null;
            }
        }

        $properties = [];
        $required = [];

        foreach ($step->fields as $field) {
            if (! $field instanceof FormFieldData) {
                return null;
            }

            $schema = $this->schemaFor($field);

            if ($schema === null) {
                return null;
            }

            if ($schema === []) {
                continue;
            }

            $properties[$field->key] = $schema;

            if ($field->required) {
                $required[] = $field->key;
            }
        }

        if ($properties === []) {
            return null;
        }

        return [
            'capellAgentSchema' => 1,
            'messages' => [
                'confirmForm' => __('capell-form-builder::agent.confirm_form'),
            ],
            'tools' => [[
                'name' => 'form.submit.' . $formId,
                'description' => __('capell-form-builder::agent.tools.submit_form'),
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
                'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['submitted', 'pending']],
                        'cancelled' => ['type' => 'boolean'],
                    ],
                    'required' => ['status'],
                    'additionalProperties' => false,
                ],
                'effect' => 'write',
                'binding' => ['type' => 'form', 'target' => $formId],
            ]],
        ];
    }

    /**
     * An empty schema marks a server-managed field. Null means the form has a
     * control that this bridge cannot safely submit.
     *
     * @return array<string, mixed>
     */
    private function schemaFor(FormFieldData $field): ?array
    {
        return match ($field->type) {
            FormFieldType::Hidden,
            FormFieldType::Honeypot,
            FormFieldType::Calculation => [],
            FormFieldType::Text,
            FormFieldType::Email,
            FormFieldType::Textarea => [
                'type' => 'string',
                ...($field->type === FormFieldType::Email ? ['format' => 'email'] : []),
                'maxLength' => $this->maxLength($field),
            ],
            FormFieldType::Number => ['type' => 'number'],
            FormFieldType::Select => [
                'type' => 'string',
                'enum' => array_map(static fn (string|int $value): string => (string) $value, array_keys($field->options)),
            ],
            FormFieldType::Checkbox => ['type' => 'boolean'],
            FormFieldType::File,
            FormFieldType::Payment => null,
        };
    }

    private function maxLength(FormFieldData $field): int
    {
        foreach ($field->validationRules as $rule) {
            if (! str_starts_with($rule, 'max:')) {
                continue;
            }

            $value = substr($rule, 4);

            if (ctype_digit($value)) {
                return max(1, min(10000, (int) $value));
            }
        }

        return 255;
    }
}

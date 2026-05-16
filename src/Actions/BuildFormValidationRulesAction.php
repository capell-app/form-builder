<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsAction;

class BuildFormValidationRulesAction
{
    use AsAction;

    private const DEFAULT_SHORT_TEXT_MAX_LENGTH = 255;

    private const DEFAULT_LONG_TEXT_MAX_LENGTH = 10000;

    /**
     * @return array<string, array<int, string>>
     */
    public function handle(Form $form): array
    {
        $rules = [];

        foreach ($form->schema ?? [] as $field) {
            $rules[$field->key] = $this->rulesForField($field);
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function rulesForField(FormFieldData $field): array
    {
        if ($field->type === FormFieldType::Honeypot) {
            return ['nullable', 'prohibited'];
        }

        $rules = [$field->required ? 'required' : 'nullable'];

        if (in_array($field->type, [FormFieldType::Text, FormFieldType::Textarea, FormFieldType::Hidden], true)) {
            $rules[] = 'string';
        }

        if ($field->type === FormFieldType::Email) {
            $rules[] = 'email';
        }

        if ($field->type === FormFieldType::Select) {
            $rules[] = 'string';
            $rules[] = 'in:' . implode(',', array_keys($field->options));
        }

        if ($field->type === FormFieldType::Checkbox) {
            $rules[] = $field->required ? 'accepted' : 'boolean';
        }

        $rules = $this->applyDefaultMaxRule($rules, $field);

        return array_values(array_unique([
            ...$rules,
            ...$this->allowedEditorRules($field->validationRules, $field),
        ]));
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, string>
     */
    private function allowedEditorRules(array $rules, FormFieldData $field): array
    {
        return array_values(array_filter(array_map(
            fn (string $rule): ?string => $this->normalizeEditorRule($rule, $field),
            $rules,
        )));
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, string>
     */
    private function applyDefaultMaxRule(array $rules, FormFieldData $field): array
    {
        $defaultMax = $this->defaultMaxLength($field);

        if ($defaultMax === null) {
            return $rules;
        }

        return [
            ...$rules,
            'max:' . $defaultMax,
        ];
    }

    private function normalizeEditorRule(string $rule, FormFieldData $field): ?string
    {
        if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1) {
            $max = (int) $matches[1];
            $upperBound = $this->defaultMaxLength($field);

            return 'max:' . ($upperBound === null ? $max : min($max, $upperBound));
        }

        if (preg_match('/^(min|size):\d+$/', $rule) === 1) {
            return $rule;
        }

        return in_array($rule, ['email', 'url', 'alpha', 'alpha_dash', 'alpha_num'], true) ? $rule : null;
    }

    private function defaultMaxLength(FormFieldData $field): ?int
    {
        return match ($field->type) {
            FormFieldType::Text, FormFieldType::Email, FormFieldType::Hidden, FormFieldType::Select => self::DEFAULT_SHORT_TEXT_MAX_LENGTH,
            FormFieldType::Textarea => self::DEFAULT_LONG_TEXT_MAX_LENGTH,
            default => null,
        };
    }
}

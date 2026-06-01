<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static array<string, array<int, string>> run(Form $form, array<string, mixed> $input = [])
 */
class BuildFormValidationRulesAction
{
    use AsAction;

    private const int DEFAULT_SHORT_TEXT_MAX_LENGTH = 255;

    private const int DEFAULT_LONG_TEXT_MAX_LENGTH = 10000;

    private const int DEFAULT_FILE_MAX_KILOBYTES = 10240;

    private const int MAX_FILE_MAX_KILOBYTES = 51200;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string>>
     */
    public function handle(Form $form, array $input = []): array
    {
        $rules = [];

        foreach (ResolveVisibleFormFieldsAction::run($form, $input) as $field) {
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

        if (in_array($field->type, [FormFieldType::Number, FormFieldType::Calculation], true)) {
            $rules[] = 'numeric';
        }

        if ($field->type === FormFieldType::File) {
            $rules[] = 'file';
            $rules[] = 'max:' . $this->fileMaxKilobytes($field);

            $fileTypes = $this->safeFileTypes($field);

            if ($fileTypes !== []) {
                $rules[] = 'mimes:' . implode(',', $fileTypes);
            }
        }

        if ($field->type === FormFieldType::Payment) {
            $rules[] = 'integer';
            $rules[] = 'min:1';
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
        return array_values(array_filter(
            array_map(
                fn (string $rule): ?string => $this->normalizeEditorRule($rule, $field),
                $rules,
            ),
            static fn (?string $rule): bool => $rule !== null,
        ));
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

        if (preg_match('/^mimes:[a-z0-9_,]+$/', $rule) === 1 && $field->type === FormFieldType::File) {
            return $rule;
        }

        return in_array($rule, ['email', 'url', 'alpha', 'alpha_dash', 'alpha_num', 'numeric', 'integer'], true) ? $rule : null;
    }

    private function defaultMaxLength(FormFieldData $field): ?int
    {
        return match ($field->type) {
            FormFieldType::Text, FormFieldType::Email, FormFieldType::Hidden, FormFieldType::Select => self::DEFAULT_SHORT_TEXT_MAX_LENGTH,
            FormFieldType::Textarea => self::DEFAULT_LONG_TEXT_MAX_LENGTH,
            default => null,
        };
    }

    private function fileMaxKilobytes(FormFieldData $field): int
    {
        if ($field->maxFileSizeKilobytes === null) {
            return self::DEFAULT_FILE_MAX_KILOBYTES;
        }

        return max(1, min($field->maxFileSizeKilobytes, self::MAX_FILE_MAX_KILOBYTES));
    }

    /**
     * @return array<int, string>
     */
    private function safeFileTypes(FormFieldData $field): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (string $type): string => strtolower(trim($type, " \t\n\r\0\x0B.")),
                $field->acceptedFileTypes,
            ),
            static fn (string $type): bool => preg_match('/^[a-z0-9]+$/', $type) === 1,
        )));
    }
}

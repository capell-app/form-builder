<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Support\EditorValidationRuleGrammar;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, array<int, string>> run(Form $form, array<string, mixed> $input = [])
 */
class BuildFormValidationRulesAction
{
    use AsFake;
    use AsObject;

    /** @var list<string> */
    private const array DEFAULT_FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'];

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

            // An empty list never means "any file": fall back to a conservative document and image allow-list.
            $rules[] = 'mimes:' . implode(',', $fileTypes === [] ? self::DEFAULT_FILE_TYPES : $fileTypes);
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
                fn (string $rule): ?string => EditorValidationRuleGrammar::normalize($rule, $field->type),
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
        $defaultMax = EditorValidationRuleGrammar::maxLengthCap($field->type);

        if ($defaultMax === null) {
            return $rules;
        }

        return [
            ...$rules,
            'max:' . $defaultMax,
        ];
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

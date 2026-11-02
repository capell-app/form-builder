<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Capell\FormBuilder\Enums\FormFieldType;

/**
 * The closed set of validation rules an editor may attach to a form field.
 * Public submissions and authoring-time checks share this grammar so the
 * editor is told about exactly the rules the public form will ignore or cap.
 */
final class EditorValidationRuleGrammar
{
    public const int DEFAULT_SHORT_TEXT_MAX_LENGTH = 255;

    public const int DEFAULT_LONG_TEXT_MAX_LENGTH = 10000;

    /** @var array<int, string> */
    public const array PLAIN_RULES = ['email', 'url', 'alpha', 'alpha_dash', 'alpha_num', 'numeric', 'integer'];

    /** @var array<int, string> */
    public const array PARAMETERISED_RULES = ['max:N', 'min:N', 'size:N', 'mimes:ext,ext'];

    /**
     * Returns the rule that the public form applies, or null when the rule is ignored.
     */
    public static function normalize(string $rule, FormFieldType $type): ?string
    {
        if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1) {
            $max = (int) $matches[1];
            $upperBound = self::maxLengthCap($type);

            return 'max:' . ($upperBound === null ? $max : min($max, $upperBound));
        }

        if (preg_match('/^(min|size):\d+$/', $rule) === 1) {
            return $rule;
        }

        if (preg_match('/^mimes:[a-z0-9_,]+$/', $rule) === 1) {
            return $type === FormFieldType::File ? $rule : null;
        }

        return in_array($rule, self::PLAIN_RULES, true) ? $rule : null;
    }

    /**
     * Explains why an authored rule would not be enforced as written, or returns null when it is.
     */
    public static function problem(string $rule, FormFieldType $type): ?string
    {
        if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1) {
            $cap = self::maxLengthCap($type);

            return $cap !== null && (int) $matches[1] > $cap
                ? (string) __('capell-form-builder::form.admin.validation_rule_exceeds_cap', ['rule' => $rule, 'max' => $cap])
                : null;
        }

        if (preg_match('/^mimes:[a-z0-9_,]+$/', $rule) === 1 && $type !== FormFieldType::File) {
            return (string) __('capell-form-builder::form.admin.validation_rule_file_only', ['rule' => $rule]);
        }

        if (self::normalize($rule, $type) !== null) {
            return null;
        }

        return (string) __('capell-form-builder::form.admin.validation_rule_unsupported', [
            'rule' => $rule,
            'supported' => implode(', ', [...self::PLAIN_RULES, ...self::PARAMETERISED_RULES]),
        ]);
    }

    public static function maxLengthCap(FormFieldType $type): ?int
    {
        return match ($type) {
            FormFieldType::Text, FormFieldType::Email, FormFieldType::Hidden, FormFieldType::Select => self::DEFAULT_SHORT_TEXT_MAX_LENGTH,
            FormFieldType::Textarea => self::DEFAULT_LONG_TEXT_MAX_LENGTH,
            default => null,
        };
    }
}

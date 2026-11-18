<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Enums\FormFieldType;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One entry of a SiteSpec's `packageData.forms` block: a form to create on the site, with the
 * address its messages go to and the page it belongs on.
 */
final readonly class SiteSpecFormData
{
    /** Field types a SiteSpec may ask for; anything richer is set up in the form editor. */
    public const array FIELD_TYPES = [
        FormFieldType::Text,
        FormFieldType::Email,
        FormFieldType::Textarea,
        FormFieldType::Checkbox,
    ];

    /** The hidden field bots fill in and people never see. A spec may not use its key for a field of its own. */
    public const string HONEYPOT_KEY = 'company_website';

    /** @var list<string> */
    public const array RESERVED_FIELD_KEYS = [self::HONEYPOT_KEY];

    public const int MAX_FORMS = 10;

    public const int MAX_FIELDS = 30;

    public const int MAX_NAME_LENGTH = 255;

    public const int MAX_LABEL_LENGTH = 255;

    public const int MAX_SUCCESS_MESSAGE_LENGTH = 1000;

    /**
     * @param  list<FormFieldData>  $fields
     */
    public function __construct(
        public string $handle,
        public string $name,
        public array $fields,
        public ?string $notify = null,
        public ?string $successMessage = null,
        public ?string $placeOnPage = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $entry
     *
     * @throws InvalidArgumentException when the entry is not a usable form
     */
    public static function fromSpec(array $entry): self
    {
        $handle = Str::slug(self::string($entry, 'handle') ?? '');
        $name = self::string($entry, 'name');
        $notify = self::string($entry, 'notify');
        $successMessage = self::string($entry, 'successMessage');
        $rawFields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];

        self::refuseIf($handle === '' || $name === null, (string) __('capell-form-builder::form.site_spec.missing_handle_or_name'));
        self::refuseIf(mb_strlen((string) $name) > self::MAX_NAME_LENGTH, (string) __('capell-form-builder::form.site_spec.name_too_long', ['form' => $handle, 'max' => self::MAX_NAME_LENGTH]));
        self::refuseIf($successMessage !== null && mb_strlen($successMessage) > self::MAX_SUCCESS_MESSAGE_LENGTH, (string) __('capell-form-builder::form.site_spec.success_message_too_long', ['form' => $handle, 'max' => self::MAX_SUCCESS_MESSAGE_LENGTH]));
        self::refuseIf($notify !== null && filter_var($notify, FILTER_VALIDATE_EMAIL) === false, (string) __('capell-form-builder::form.site_spec.notify_not_email', ['form' => $handle]));
        self::refuseIf($rawFields === [], (string) __('capell-form-builder::form.site_spec.no_fields', ['form' => $handle]));
        self::refuseIf(count($rawFields) > self::MAX_FIELDS, (string) __('capell-form-builder::form.site_spec.too_many_fields', ['form' => $handle, 'max' => self::MAX_FIELDS]));

        $fields = [];

        foreach ($rawFields as $field) {
            $fields[] = self::field(is_array($field) ? $field : [], $handle);
        }

        $keys = array_map(static fn (FormFieldData $field): string => $field->key, $fields);
        self::refuseIf(count($keys) !== count(array_unique($keys)), (string) __('capell-form-builder::form.site_spec.repeated_field_key', ['form' => $handle]));

        return new self(
            handle: $handle,
            name: (string) $name,
            fields: $fields,
            notify: $notify,
            successMessage: $successMessage,
            placeOnPage: self::string($entry, 'placeOnPage'),
        );
    }

    /** The first email field, which the form replies to. */
    public function visitorEmailFieldKey(): ?string
    {
        foreach ($this->fields as $field) {
            if ($field->type === FormFieldType::Email) {
                return $field->key;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $field
     */
    private static function field(array $field, string $handle): FormFieldData
    {
        $key = Str::snake(self::string($field, 'key') ?? '');
        $label = self::string($field, 'label');
        $type = FormFieldType::tryFrom(self::string($field, 'type') ?? '');

        self::refuseIf($key === '' || $label === null, (string) __('capell-form-builder::form.site_spec.field_missing_key_or_label', ['form' => $handle]));
        self::refuseIf(in_array($key, self::RESERVED_FIELD_KEYS, true), (string) __('capell-form-builder::form.site_spec.field_key_reserved', ['form' => $handle, 'field' => $key]));
        self::refuseIf(mb_strlen((string) $label) > self::MAX_LABEL_LENGTH, (string) __('capell-form-builder::form.site_spec.field_label_too_long', ['form' => $handle, 'field' => $key, 'max' => self::MAX_LABEL_LENGTH]));
        self::refuseIf(! in_array($type, self::FIELD_TYPES, true), (string) __('capell-form-builder::form.site_spec.field_type_unsupported', ['form' => $handle, 'field' => $key]));

        return new FormFieldData(
            key: $key,
            label: (string) $label,
            type: $type ?? FormFieldType::Text,
            required: ($field['required'] ?? false) === true,
        );
    }

    /** @throws InvalidArgumentException */
    private static function refuseIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

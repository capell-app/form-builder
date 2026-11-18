<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Enums\FormFieldType;
use Illuminate\Support\Facades\Lang;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class FormFieldData extends Data
{
    /** Labels stored as one of these translation keys are shown in the reader's locale. */
    public const string TRANSLATED_LABEL_PREFIX = 'capell-form-builder::';

    /**
     * @param  array<string, string>  $options
     * @param  array<int, string>  $validationRules
     * @param  array<int, string>  $acceptedFileTypes
     * @param  DataCollection<int, FormFieldConditionData>|null  $visibilityConditions
     */
    public function __construct(
        public string $key,
        public string $label,
        public FormFieldType $type = FormFieldType::Text,
        public bool $required = false,
        public ?string $placeholder = null,
        public ?string $helpText = null,
        public array $options = [],
        public mixed $defaultValue = null,
        public array $validationRules = [],
        public ?string $stepKey = null,
        public ?string $calculationExpression = null,
        public array $acceptedFileTypes = [],
        public ?int $maxFileSizeKilobytes = null,
        public ?int $paymentAmountCents = null,
        public ?string $paymentCurrency = null,
        #[DataCollectionOf(FormFieldConditionData::class)]
        public ?DataCollection $visibilityConditions = null,
    ) {
        $this->visibilityConditions ??= new DataCollection(FormFieldConditionData::class, []);
    }

    public static function resolveLabel(string $label): string
    {
        if (str_starts_with($label, self::TRANSLATED_LABEL_PREFIX) && Lang::has($label)) {
            return (string) match ($label) {
                'capell-form-builder::form.site_spec_honeypot_label' => __('capell-form-builder::form.site_spec_honeypot_label'),
                default => __($label),
            };
        }

        return $label;
    }

    /**
     * @return array<int, FormFieldConditionData>
     */
    public function visibilityConditions(): array
    {
        return $this->visibilityConditions?->items() ?? [];
    }

    /**
     * The label to show a person. A label stored as one of this package's translation keys, such as the
     * honeypot label a site spec adds, is resolved in the current locale rather than frozen at creation.
     */
    public function displayLabel(): string
    {
        return self::resolveLabel($this->label);
    }
}

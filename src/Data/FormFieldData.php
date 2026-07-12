<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Enums\FormFieldType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class FormFieldData extends Data
{
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

    /**
     * @return array<int, FormFieldConditionData>
     */
    public function visibilityConditions(): array
    {
        return $this->visibilityConditions?->items() ?? [];
    }
}

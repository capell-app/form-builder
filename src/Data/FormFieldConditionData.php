<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Enums\FormFieldConditionOperator;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class FormFieldConditionData extends Data
{
    public function __construct(
        public string $fieldKey,
        public FormFieldConditionOperator $operator = FormFieldConditionOperator::Equals,
        public mixed $value = null,
    ) {}
}

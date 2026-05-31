<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class FormStepData extends Data
{
    /**
     * @param  Collection<int, FormFieldData>  $fields
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Collection $fields,
    ) {}
}

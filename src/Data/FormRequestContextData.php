<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Spatie\LaravelData\Data;

final class FormRequestContextData extends Data
{
    public function __construct(
        public readonly string $origin,
        public readonly string $path,
        public readonly string $basePath,
    ) {}
}

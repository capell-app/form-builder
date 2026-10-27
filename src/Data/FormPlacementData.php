<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

final readonly class FormPlacementData
{
    public function __construct(
        public ?string $handle,
        public string $siteName,
        public ?string $submissionsUrl,
        public bool $storesSubmissions,
    ) {}
}

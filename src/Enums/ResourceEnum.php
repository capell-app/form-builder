<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Enums;

use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;

enum ResourceEnum: string
{
    case Forms = FormResource::class;
    case Submissions = SubmissionResource::class;
}

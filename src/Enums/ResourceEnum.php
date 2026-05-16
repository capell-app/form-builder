<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Enums;

use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;

enum ResourceEnum: string
{
    case Submissions = SubmissionResource::class;
}

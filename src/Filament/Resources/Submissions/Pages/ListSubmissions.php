<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Submissions\Pages;

use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Filament\Resources\Pages\ListRecords;

final class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;
}

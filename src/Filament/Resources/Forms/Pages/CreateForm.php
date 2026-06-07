<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateForm extends CreateRecord
{
    protected static string $resource = FormResource::class;
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListForms extends ListRecords
{
    protected static string $resource = FormResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

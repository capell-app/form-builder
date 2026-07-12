<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

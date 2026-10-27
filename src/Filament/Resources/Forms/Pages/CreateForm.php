<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Actions\ValidateFormFieldKeysAction;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Models\Form;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateForm extends CreateRecord
{
    protected static string $resource = FormResource::class;

    protected function beforeValidate(): void
    {
        $schema = $this->data['schema'] ?? [];

        /** @var array<array<string, mixed>> $schema */
        ValidateFormFieldKeysAction::run($schema);
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        return $record instanceof Form && FormResource::canEdit($record)
            ? FormResource::getUrl('edit', ['record' => $record, 'action' => 'placement'])
            : parent::getRedirectUrl();
    }
}

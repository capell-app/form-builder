<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Actions\BuildFormPlacementAction;
use Capell\FormBuilder\Actions\ValidateFormFieldKeysAction;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Models\Form;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Override;

final class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function beforeValidate(): void
    {
        $schema = $this->data['schema'] ?? [];

        /** @var array<array<string, mixed>> $schema */
        ValidateFormFieldKeysAction::run($schema);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('placement')
                ->label(__('capell-form-builder::placement.title'))
                ->visible(fn (): bool => $this->getRecord() instanceof Form && FormResource::canEdit($this->getRecord()))
                ->modalContent(function (): View {
                    $record = $this->getRecord();
                    abort_unless($record instanceof Form, 404);

                    return view('capell-form-builder::filament.form-placement', [
                        'placement' => BuildFormPlacementAction::run($record),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('capell-form-builder::placement.close')),
            DeleteAction::make()
                ->modalHeading(__('capell-form-builder::form.delete_modal.heading'))
                ->modalDescription(__('capell-form-builder::form.delete_modal.description')),
        ];
    }
}

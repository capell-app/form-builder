<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms\Pages;

use Capell\FormBuilder\Actions\BuildFormPlacementAction;
use Capell\FormBuilder\Actions\DeleteFormSubmissionsAction;
use Capell\FormBuilder\Actions\ValidateFormFieldKeysAction;
use Capell\FormBuilder\Actions\ValidateFormSchemaAuthoringAction;
use Capell\FormBuilder\Exceptions\FormHasSubmissionsUnderLegalHoldException;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Models\Form;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Override;
use Throwable;

final class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function beforeValidate(): void
    {
        $schema = $this->data['schema'] ?? [];

        /** @var array<array<string, mixed>> $schema */
        ValidateFormFieldKeysAction::run($schema);
        ValidateFormSchemaAuthoringAction::run($schema);
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
                ->modalDescription(__('capell-form-builder::form.delete_modal.description'))
                ->using(static function (Form $record, DeleteAction $action): bool {
                    try {
                        DeleteFormSubmissionsAction::run($record);

                        return (bool) $record->delete();
                    } catch (Throwable $exception) {
                        throw_unless($exception instanceof FormHasSubmissionsUnderLegalHoldException, $exception);

                        // Some submissions had to be kept, so the form was kept too: say how many and why.
                        Notification::make('form-builder-form-delete-blocked')
                            ->title($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                        $action->halt();

                        return false;
                    }
                }),
        ];
    }
}

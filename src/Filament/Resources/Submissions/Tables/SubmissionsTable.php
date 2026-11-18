<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Submissions\Tables;

use Capell\FormBuilder\Actions\ArchiveSubmissionAction;
use Capell\FormBuilder\Actions\BuildSubmissionsCsvAction;
use Capell\FormBuilder\Actions\DeleteSubmissionAction;
use Capell\FormBuilder\Actions\ListSubmissionUploadsAction;
use Capell\FormBuilder\Actions\MarkSubmissionReadAction;
use Capell\FormBuilder\Actions\MarkSubmissionSpamAction;
use Capell\FormBuilder\Actions\ReplyToSubmissionAction;
use Capell\FormBuilder\Actions\ResolveSubmissionReplyAddressAction;
use Capell\FormBuilder\Actions\ResolveSubmissionUploadDownloadAction;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionSiteAccess;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => SubmissionSiteAccess::applyToQuery($query->with(['form', 'site'])))
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateHeading(__('capell-form-builder::table.submissions_empty'))
            ->columns([
                TextColumn::make('form.name')
                    ->label(__('capell-form-builder::table.form'))
                    ->sortable(),
                TextColumn::make('reply_to')
                    ->label(__('capell-form-builder::table.reply_to'))
                    ->state(fn (Submission $record): string => ResolveSubmissionReplyAddressAction::run($record) ?? '—')
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('capell-form-builder::table.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state): string => __('capell-form-builder::generic.submission_status.' . $state->value)),
                TextColumn::make('site.name')
                    ->label(__('capell-form-builder::table.site'))
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label(__('capell-form-builder::table.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('form_id')
                    ->label(__('capell-form-builder::table.form'))
                    ->relationship(
                        'form',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => SubmissionSiteAccess::applyToSiteScopedQuery($query),
                    ),
                Filter::make('hide_spam')
                    ->label(__('capell-form-builder::table.hide_spam'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', SubmissionStatus::Spam->value)),
                SelectFilter::make('status')
                    ->label(__('capell-form-builder::table.status'))
                    ->options(collect(SubmissionStatus::cases())
                        ->mapWithKeys(fn (SubmissionStatus $status): array => [
                            $status->value => __('capell-form-builder::generic.submission_status.' . $status->value),
                        ])
                        ->all()),
            ])
            ->recordActions([
                Action::make('view_payload')
                    ->label(__('capell-form-builder::table.payload'))
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Submission $record): bool => Gate::allows('view', $record))
                    ->modalHeading(__('capell-form-builder::table.payload'))
                    ->modalSubmitAction(false)
                    ->modalContent(function (Submission $record): View {
                        Gate::authorize('view', $record);

                        return view('capell-form-builder::filament.submissions.payload', [
                            'submission' => $record->loadMissing('form'),
                        ]);
                    }),
                Action::make('mark_read')
                    ->label(__('capell-form-builder::table.mark_read'))
                    ->icon('heroicon-o-envelope-open')
                    ->color('gray')
                    ->visible(fn (Submission $record): bool => $record->status !== SubmissionStatus::Read
                        && Gate::allows('update', $record))
                    ->action(function (Submission $record): void {
                        Gate::authorize('update', $record);

                        MarkSubmissionReadAction::run($record);

                        Notification::make('form-builder-submission-status-updated')
                            ->title(__('capell-form-builder::message.status_updated'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
                Action::make('archive')
                    ->label(__('capell-form-builder::table.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('capell-form-builder::table.archive_heading'))
                    ->modalDescription(__('capell-form-builder::table.archive_description'))
                    ->visible(fn (Submission $record): bool => $record->status !== SubmissionStatus::Archived
                        && Gate::allows('update', $record))
                    ->action(function (Submission $record): void {
                        Gate::authorize('update', $record);

                        ArchiveSubmissionAction::run($record);

                        Notification::make('form-builder-submission-status-updated')
                            ->title(__('capell-form-builder::message.status_updated'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
                Action::make('toggle_legal_hold')
                    ->label(fn (Submission $record): string => $record->legal_hold
                        ? __('capell-form-builder::table.release_legal_hold')
                        : __('capell-form-builder::table.apply_legal_hold'))
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Submission $record): string => $record->legal_hold
                        ? __('capell-form-builder::table.release_legal_hold_heading')
                        : __('capell-form-builder::table.apply_legal_hold_heading'))
                    ->modalDescription(fn (Submission $record): string => $record->legal_hold
                        ? __('capell-form-builder::table.release_legal_hold_description')
                        : __('capell-form-builder::table.apply_legal_hold_description'))
                    ->visible(fn (Submission $record): bool => Gate::allows('update', $record))
                    ->action(function (Submission $record): void {
                        Gate::authorize('update', $record);
                        $record->forceFill(['legal_hold' => ! $record->legal_hold])->save();

                        Notification::make('form-builder-submission-legal-hold-updated')
                            ->title($record->legal_hold
                                ? __('capell-form-builder::message.legal_hold_applied')
                                : __('capell-form-builder::message.legal_hold_released'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
                Action::make('download_upload')
                    ->label(__('capell-form-builder::table.download_upload'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Submission $record): bool => Gate::allows('view', $record)
                        && ListSubmissionUploadsAction::run($record) !== [])
                    ->schema(fn (Submission $record): array => [
                        Select::make('upload')
                            ->label(__('capell-form-builder::table.download_upload_choose'))
                            ->options(ListSubmissionUploadsAction::run($record))
                            ->default(array_key_first(ListSubmissionUploadsAction::run($record)))
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Submission $record, array $data) {
                        Gate::authorize('view', $record);

                        return ResolveSubmissionUploadDownloadAction::run($record, (string) $data['upload']);
                    }),
                Action::make('mark_spam')
                    ->label(__('capell-form-builder::table.mark_spam'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('capell-form-builder::table.mark_spam_heading'))
                    ->modalDescription(__('capell-form-builder::table.mark_spam_description'))
                    ->visible(fn (Submission $record): bool => $record->status !== SubmissionStatus::Spam
                        && Gate::allows('update', $record))
                    ->action(function (Submission $record): void {
                        Gate::authorize('update', $record);

                        MarkSubmissionSpamAction::run($record);

                        Notification::make('form-builder-submission-status-updated')
                            ->title(__('capell-form-builder::message.status_updated'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
                Action::make('delete')
                    ->label(__('capell-form-builder::table.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('capell-form-builder::table.delete_heading'))
                    ->modalDescription(__('capell-form-builder::table.delete_description'))
                    ->visible(fn (Submission $record): bool => ! $record->legal_hold && Gate::allows('delete', $record))
                    ->action(function (Submission $record): void {
                        Gate::authorize('delete', $record);

                        DeleteSubmissionAction::run($record);

                        Notification::make('form-builder-submission-deleted')
                            ->title(__('capell-form-builder::message.submission_deleted'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
                Action::make('reply')
                    ->label(__('capell-form-builder::table.reply'))
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Submission $record): bool => ResolveSubmissionReplyAddressAction::run($record) !== null
                        && Gate::allows('reply', $record))
                    ->modalDescription(fn (Submission $record): string => __('capell-form-builder::message.reply_recipient', [
                        'email' => ResolveSubmissionReplyAddressAction::run($record) ?? __('capell-form-builder::message.reply_recipient_missing'),
                    ]))
                    ->schema([
                        TextInput::make('subject')
                            ->label(__('capell-form-builder::table.reply_subject'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('message')
                            ->label(__('capell-form-builder::table.reply_message'))
                            ->required()
                            ->rows(8),
                    ])
                    ->action(function (Submission $record, array $data): void {
                        Gate::authorize('reply', $record);

                        ReplyToSubmissionAction::run(
                            submission: $record,
                            subject: (string) $data['subject'],
                            message: (string) $data['message'],
                        );

                        Notification::make('form-builder-reply-sent')
                            ->title(__('capell-form-builder::message.reply_sent'))
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('capell-form-builder::table.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => Gate::allows('export', Submission::class))
                    ->action(function () {
                        Gate::authorize('export', Submission::class);

                        /** @var Builder<Model> $base */
                        $base = Submission::query();
                        /** @var Builder<Submission> $scope */
                        $scope = SubmissionSiteAccess::applyToQuery(
                            SubmissionSiteAccess::applyToQuery($base),
                            null,
                            SubmissionSiteAccess::EXPORT_ABILITIES,
                        );

                        return response()->streamDownload(static function () use ($scope): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            BuildSubmissionsCsvAction::make()->writeTo($stream, null, $scope);
                            fclose($stream);
                        }, 'form-submissions-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label(__('capell-form-builder::table.delete_selected'))
                    ->modalHeading(__('capell-form-builder::table.delete_selected_heading'))
                    ->modalDescription(__('capell-form-builder::table.delete_selected_description'))
                    ->visible(fn (): bool => Gate::allows('deleteAny', Submission::class))
                    ->action(static function (Collection $records): void {
                        $skipped = 0;

                        foreach ($records as $submission) {
                            // A refusal (legal hold, no permission) must not stop the rest of the selection.
                            if (! $submission instanceof Submission || ! Gate::allows('delete', $submission) || ! DeleteSubmissionAction::run($submission)) {
                                $skipped++;
                            }
                        }

                        if ($skipped > 0) {
                            Notification::make('form-builder-submissions-delete-skipped')
                                ->title(__('capell-form-builder::message.submissions_delete_skipped', ['count' => $skipped]))
                                ->icon('heroicon-o-exclamation-triangle')
                                ->iconColor('warning')
                                ->send();
                        }
                    }),
            ]);
    }
}

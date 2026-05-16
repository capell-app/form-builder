<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Submissions\Tables;

use Capell\FormBuilder\Actions\ArchiveSubmissionAction;
use Capell\FormBuilder\Actions\MarkSubmissionReadAction;
use Capell\FormBuilder\Actions\MarkSubmissionSpamAction;
use Capell\FormBuilder\Actions\ReplyToSubmissionAction;
use Capell\FormBuilder\Actions\ResolveSubmissionReplyAddressAction;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionSiteAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
                Action::make('mark_spam')
                    ->label(__('capell-form-builder::table.mark_spam'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
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
                Action::make('reply')
                    ->label(__('capell-form-builder::table.reply'))
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Submission $record): bool => ResolveSubmissionReplyAddressAction::run($record) !== null
                        && Gate::allows('reply', $record))
                    ->modalDescription(fn (Submission $record): string => __('capell-form-builder::message.reply_recipient', [
                        'email' => ResolveSubmissionReplyAddressAction::run($record) ?? __('capell-form-builder::message.reply_recipient_missing'),
                    ]))
                    ->form([
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
            ]);
    }
}

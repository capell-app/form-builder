<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Submissions;

use BackedEnum;
use Capell\FormBuilder\Filament\Resources\Submissions\Pages\ListSubmissions;
use Capell\FormBuilder\Filament\Resources\Submissions\Tables\SubmissionsTable;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionSiteAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Override;

final class SubmissionResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Inbox;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return SubmissionsTable::configure($table);
    }

    #[Override]
    public static function getModel(): string
    {
        return Submission::class;
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return SubmissionSiteAccess::applyToQuery(parent::getEloquentQuery()->with(['form', 'site']));
    }

    #[Override]
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof Authenticatable && Gate::forUser($user)->allows('viewAny', Submission::class);
    }

    #[Override]
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof Authenticatable && Gate::forUser($user)->allows('viewAny', Submission::class);
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-form-builder::navigation.submissions');
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    #[Override]
    public static function getNavigationParentItem(): string
    {
        return __('capell-admin::navigation.marketing_studio');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSubmissions::route('/'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Filament\Resources\Forms;

use BackedEnum;
use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Capell\Admin\Support\SiteScope;
use Capell\FormBuilder\Enums\FormFieldConditionOperator;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\CreateForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\EditForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\ListForms;
use Capell\FormBuilder\Models\Form;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Override;

final class FormResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->schema([
                Section::make(__('capell-form-builder::form.admin.sections.details'))
                    ->schema([
                        SiteSelect::make('site_id')->required(),
                        TextInput::make('name')
                            ->label(__('capell-form-builder::form.admin.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('handle')
                            ->label(__('capell-form-builder::form.admin.fields.handle'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('capell-form-builder::form.admin.fields.is_active'))
                            ->default(true),
                        Textarea::make('description')
                            ->label(__('capell-form-builder::form.admin.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('capell-form-builder::form.admin.sections.schema'))
                    ->schema([
                        Repeater::make('schema')
                            ->label(__('capell-form-builder::form.admin.fields.schema'))
                            ->schema(self::fieldSchema())
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => is_string($state['label'] ?? null) ? $state['label'] : null)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('capell-form-builder::form.admin.sections.settings'))
                    ->schema([
                        Toggle::make('settings.store_submissions')
                            ->label(__('capell-form-builder::form.admin.fields.store_submissions'))
                            ->default(true),
                        TextInput::make('settings.notification_email')
                            ->label(__('capell-form-builder::form.admin.fields.notification_email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('settings.autoresponder_subject')
                            ->label(__('capell-form-builder::form.admin.fields.autoresponder_subject'))
                            ->maxLength(255),
                        Textarea::make('settings.autoresponder_body')
                            ->label(__('capell-form-builder::form.admin.fields.autoresponder_body'))
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('settings.success_redirect_url')
                            ->label(__('capell-form-builder::form.admin.fields.success_redirect_url'))
                            ->maxLength(2048),
                        TextInput::make('settings.webhook_url')
                            ->label(__('capell-form-builder::form.admin.fields.webhook_url'))
                            ->url()
                            ->maxLength(2048),
                        Toggle::make('settings.collect_ip_address')
                            ->label(__('capell-form-builder::form.admin.fields.collect_ip_address'))
                            ->default(true),
                        Toggle::make('settings.collect_user_agent')
                            ->label(__('capell-form-builder::form.admin.fields.collect_user_agent'))
                            ->default(true),
                        Textarea::make('settings.success_message')
                            ->label(__('capell-form-builder::form.admin.fields.success_message'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => SiteScope::applyForCurrentActor($query->with('site')))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('capell-form-builder::form.admin.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label(__('capell-form-builder::form.admin.fields.handle'))
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label(__('capell-form-builder::table.site'))
                    ->toggleable(),
                TextColumn::make('submissions_count')
                    ->label(__('capell-form-builder::form.admin.fields.submissions_count'))
                    ->counts('submissions')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('capell-form-builder::form.admin.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('capell-form-builder::form.admin.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('capell-form-builder::form.admin.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    #[Override]
    public static function getModel(): string
    {
        return Form::class;
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return SiteScope::applyForCurrentActor(parent::getEloquentQuery());
    }

    #[Override]
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof Authenticatable && Gate::forUser($user)->allows('viewAny', Form::class);
    }

    #[Override]
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof Authenticatable && Gate::forUser($user)->allows('viewAny', Form::class);
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-form-builder::navigation.forms');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return (string) __('capell-form-builder::navigation.forms');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return (string) __('capell-form-builder::navigation.form');
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    #[Override]
    public static function getNavigationParentItem(): string
    {
        return (string) __('capell-admin::navigation.marketing_studio');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListForms::route('/'),
            'create' => CreateForm::route('/create'),
            'edit' => EditForm::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function fieldSchema(): array
    {
        return [
            TextInput::make('key')
                ->label(__('capell-form-builder::form.admin.fields.field_key'))
                ->required()
                ->alphaDash()
                ->maxLength(255),
            TextInput::make('label')
                ->label(__('capell-form-builder::form.admin.fields.field_label'))
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label(__('capell-form-builder::form.admin.fields.field_type'))
                ->options(FormFieldType::class)
                ->required()
                ->default(FormFieldType::Text->value),
            Toggle::make('required')
                ->label(__('capell-form-builder::form.admin.fields.required')),
            TextInput::make('placeholder')
                ->label(__('capell-form-builder::form.admin.fields.placeholder'))
                ->maxLength(255),
            Textarea::make('help_text')
                ->label(__('capell-form-builder::form.admin.fields.help_text'))
                ->rows(2),
            KeyValue::make('options')
                ->label(__('capell-form-builder::form.admin.fields.options'))
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::Select->value)
                ->columnSpanFull(),
            TextInput::make('default_value')
                ->label(__('capell-form-builder::form.admin.fields.default_value')),
            TagsInput::make('validation_rules')
                ->label(__('capell-form-builder::form.admin.fields.validation_rules'))
                ->placeholder(__('capell-form-builder::form.admin.placeholders.validation_rules'))
                ->columnSpanFull(),
            TextInput::make('step_key')
                ->label(__('capell-form-builder::form.admin.fields.step_key'))
                ->maxLength(255),
            TextInput::make('calculation_expression')
                ->label(__('capell-form-builder::form.admin.fields.calculation_expression'))
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::Calculation->value)
                ->columnSpanFull(),
            TagsInput::make('accepted_file_types')
                ->label(__('capell-form-builder::form.admin.fields.accepted_file_types'))
                ->placeholder(__('capell-form-builder::form.admin.placeholders.accepted_file_types'))
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::File->value),
            TextInput::make('max_file_size_kilobytes')
                ->label(__('capell-form-builder::form.admin.fields.max_file_size_kilobytes'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::File->value),
            TextInput::make('payment_amount_cents')
                ->label(__('capell-form-builder::form.admin.fields.payment_amount_cents'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::Payment->value),
            TextInput::make('payment_currency')
                ->label(__('capell-form-builder::form.admin.fields.payment_currency'))
                ->maxLength(3)
                ->visible(fn (callable $get): bool => $get('type') === FormFieldType::Payment->value),
            Repeater::make('visibility_conditions')
                ->label(__('capell-form-builder::form.admin.fields.visibility_conditions'))
                ->schema([
                    TextInput::make('field_key')
                        ->label(__('capell-form-builder::form.admin.fields.condition_field_key'))
                        ->required()
                        ->alphaDash(),
                    Select::make('operator')
                        ->label(__('capell-form-builder::form.admin.fields.condition_operator'))
                        ->options(FormFieldConditionOperator::class)
                        ->required(),
                    TextInput::make('value')
                        ->label(__('capell-form-builder::form.admin.fields.condition_value')),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ];
    }
}

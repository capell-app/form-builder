<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Bridges\LayoutBuilder;

use Capell\Core\Support\Permissions\SiteAccess;
use Capell\FormBuilder\Models\Form;
use Capell\LayoutBuilder\Filament\Configurators\Widgets\DefaultWidgetConfigurator;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs\Tab;
use Override;

/**
 * Lets an editor choose which Form Builder form a Layout Builder widget shows.
 * The widget stores the form's handle, so each site shows its own form with
 * that handle.
 */
final class FormBuilderFormWidgetConfigurator extends DefaultWidgetConfigurator
{
    /**
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        $options = [];

        foreach (SiteAccess::current()->scope(Form::query()->active()->orderBy('name'))->get(['name', 'handle']) as $form) {
            $handle = (string) $form->handle;
            $options[$handle] ??= __('capell-form-builder::widget.form_option', ['name' => $form->name, 'handle' => $handle]);
        }

        return $options;
    }

    #[Override]
    protected function detailsTab(): Tab
    {
        return Tab::make('form_builder_form')
            ->label(__('capell-form-builder::widget.form'))
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                Select::make('meta.form_handle')
                    ->label(__('capell-form-builder::widget.form'))
                    ->helperText(__('capell-form-builder::widget.form_help'))
                    ->options(static fn (): array => self::formOptions())
                    ->searchable()
                    ->required(),
            ]);
    }
}

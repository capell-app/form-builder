<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Console\Commands;

use Capell\FormBuilder\Actions\BuildSubmissionsCsvAction;
use Capell\FormBuilder\Models\Form;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

final class ExportSubmissionsCommand extends Command
{
    protected $signature = 'capell:form-builder:export-submissions
        {--form= : Optional form id or handle to export.}
        {--path= : Optional file path to write instead of printing CSV to stdout.}';

    protected $description = 'Export Form Builder submissions as CSV.';

    public function handle(): int
    {
        $form = $this->form();

        if ($form === false) {
            return self::INVALID;
        }

        $csv = BuildSubmissionsCsvAction::run($form);
        $pathOption = $this->option('path');
        $path = is_string($pathOption) ? trim($pathOption) : '';

        if ($path === '') {
            $this->output->write($csv);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists((string) dirname($path));
        File::put($path, $csv);

        $this->components->info(__('capell-form-builder::message.export_written', [
            'path' => $path,
        ]));

        return self::SUCCESS;
    }

    private function form(): Form|false|null
    {
        $option = $this->option('form');

        if (! is_string($option) || trim($option) === '') {
            return null;
        }

        $value = trim($option);
        $form = Form::query()
            ->when(is_numeric($value), fn (Builder $query): Builder => $query->whereKey((int) $value))
            ->when(! is_numeric($value), fn (Builder $query): Builder => $query->where('handle', $value))
            ->first();

        if (! $form instanceof Form) {
            $this->components->error(__('capell-form-builder::message.export_form_not_found'));

            return false;
        }

        return $form;
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Console\Commands;

use Capell\FormBuilder\Actions\SeedFormBuilderScreenshotFixtureAction;
use Illuminate\Console\Command;
use Throwable;

final class SeedFormBuilderScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:form-builder-screenshot-fixture {--force : Confirm an intentional disposable screenshot seed}';

    protected $description = 'Seed Form Builder record state for an explicit disposable screenshot run';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        try {
            $counts = SeedFormBuilderScreenshotFixtureAction::run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Form Builder screenshot fixture initialized with %d forms.', $counts['forms']));

        return self::SUCCESS;
    }
}

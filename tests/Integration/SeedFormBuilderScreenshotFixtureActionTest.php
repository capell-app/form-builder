<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\SeedFormBuilderScreenshotFixtureAction;

function withFormBuilderScreenshotFixtureEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
    }
}

it('seeds populated forms and submissions idempotently', function (): void {
    Site::factory()->create();

    withFormBuilderScreenshotFixtureEnvironment(function (): void {
        $first = SeedFormBuilderScreenshotFixtureAction::run();
        $second = SeedFormBuilderScreenshotFixtureAction::run();

        expect($first)->toBe(['forms' => 3, 'submissions' => 4])
            ->and($second)->toBe($first);
    });
});

it('refuses to seed outside the disposable screenshot environment', function (): void {
    expect(fn (): array => SeedFormBuilderScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});

it('requires --force on the form builder screenshot fixture command', function (): void {
    $this->artisan('capell:form-builder-screenshot-fixture')->assertFailed();
});

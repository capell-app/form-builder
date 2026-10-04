<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\SeedFormBuilderScreenshotFixtureAction;
use Capell\FormBuilder\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

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

it('anchors screenshot dates to the current lifecycle', function (string $date): void {
    Date::setTestNow($date);

    try {
        $now = CarbonImmutable::now('UTC');
        Site::factory()->create();
        withFormBuilderScreenshotFixtureEnvironment(function () use ($now): void {
            SeedFormBuilderScreenshotFixtureAction::run();
            expect(Submission::query()->count())->toBe(4)
                ->and(Submission::query()->where('submitted_at', '>=', $now)->count())->toBe(0)
                ->and(Submission::query()->where('submitted_at', '<', $now->subDays(7))->count())->toBe(0);
        });
    } finally {
        Date::setTestNow();
    }
})->with(['2026-10-02 00:01:00', '2028-12-31 23:59:00']);

it('moves the same submissions forward when re-seeded on a later day', function (): void {
    Site::factory()->create();

    try {
        withFormBuilderScreenshotFixtureEnvironment(function (): void {
            Date::setTestNow('2026-10-02 12:00:00');
            SeedFormBuilderScreenshotFixtureAction::run();

            Date::setTestNow('2027-03-15 12:00:00');
            $now = CarbonImmutable::now('UTC');

            expect(SeedFormBuilderScreenshotFixtureAction::run())->toBe(['forms' => 3, 'submissions' => 4])
                ->and(Submission::query()->where('submitted_at', '<', $now->subDays(7))->count())->toBe(0)
                ->and(Submission::query()->where('submitted_at', '>=', $now)->count())->toBe(0);
        });
    } finally {
        Date::setTestNow();
    }
});

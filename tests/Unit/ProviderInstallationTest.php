<?php

declare(strict_types=1);

use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;

it('registers installed runtime once during in-process installation', function (): void {
    $surface = static function (Application $app): int {
        $listeners = $app->make(Dispatcher::class)->getRawListeners()['capell.theme-demo.forms'] ?? [];
        throw_unless(is_array($listeners), RuntimeException::class);

        return count($listeners);
    };

    $fresh = 0;
    PackageInstallationTestCase::assertFreshInstalledBoot('form-builder', static function (Application $app) use ($surface, &$fresh): void {
        $fresh = $surface($app);
        expect($fresh)->toBe(1);
    });

    PackageInstallationTestCase::assertInProcessInstallation('form-builder', static function (Application $app, Closure $refresh) use ($surface, $fresh): void {
        expect($surface($app))->toBe(0);
        $refresh();
        expect($surface($app))->toBe($fresh);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $refresh();
        expect($surface($app))->toBe($fresh)
            ->and($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});

<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\FormBuilder\Health\FormBuilderHealthCheck;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

it('reports a compatible capell api version', function (): void {
    expect(FormBuilderHealthCheck::compatibleCapellApiVersion())->toBe('^0.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = FormBuilderHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(3)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when tables exist, spam scoring is enabled, and submissions are encrypted', function (): void {
    $results = FormBuilderHealthCheck::runDiagnostics();

    expect(FormBuilderHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue();
});

it('fails the storage table check when the submissions table is missing', function (): void {
    Schema::drop('submissions');

    $check = new FormBuilderHealthCheck;

    expect($check->missingTables())->toContain('submissions')
        ->and($check->storageTablesCheck()->passed)->toBeFalse()
        ->and(FormBuilderHealthCheck::passed())->toBeFalse();
});

it('fails the spam scoring check when spam scoring is disabled', function (): void {
    Config::set('capell-form-builder.spam_scoring.enabled', false);

    $check = new FormBuilderHealthCheck;

    expect($check->spamScoringEnabled())->toBeFalse()
        ->and($check->spamScoringEnabledCheck()->passed)->toBeFalse()
        ->and(FormBuilderHealthCheck::passed())->toBeFalse();
});

it('confirms submission payload and metadata are cast through the encrypted cast', function (): void {
    $check = new FormBuilderHealthCheck;

    expect($check->unencryptedSubmissionAttributes())->toBe([])
        ->and($check->encryptedSubmissionsCheck()->passed)->toBeTrue();
});

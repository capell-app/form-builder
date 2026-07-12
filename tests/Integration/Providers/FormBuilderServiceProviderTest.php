<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Providers\FormBuilderServiceProvider;
use Capell\FormBuilder\Support\SpamProtection\NullSpamProtectionProvider;

it('registers form-builder package metadata', function (): void {
    $package = CapellCore::getPackage(FormBuilderServiceProvider::$packageName);

    expect($package->name)->toBe('capell-app/form-builder')
        ->and($package->serviceProviderClass)->toBe(FormBuilderServiceProvider::class)
        ->and($package->path)->toBe(realpath(__DIR__ . '/../../../'))
        ->and($package->getDescription())->toBe('Build site-scoped forms in Capell and capture spam-filtered, encrypted submissions into a per-site triage inbox with one-click email replies.');
});

it('registers form-builder models for Capell model enumeration', function (): void {
    $models = CapellCore::getModels();

    expect($models)->toContain(Form::class)
        ->and($models)->toContain(Submission::class);
});

it('falls back to the null spam protection provider for invalid provider config', function (): void {
    config()->set('capell-form-builder.spam_protection.provider', 'Missing\\SpamProvider');
    app()->forgetInstance(SpamProtectionProvider::class);

    expect(resolve(SpamProtectionProvider::class))->toBeInstanceOf(NullSpamProtectionProvider::class);
});

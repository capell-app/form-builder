<?php

declare(strict_types=1);

use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Providers\FormBuilderServiceProvider;
use Capell\FormBuilder\Tests\Fixtures\FormBuilderPrivacyTestSubject;
use Capell\PrivacyCenter\Support\PrivacySubjectEraserRegistry;
use Capell\PrivacyCenter\Support\PrivacySubjectExporterRegistry;

it('exports and erases matching form submissions through the privacy registries', function (): void {
    app()->singleton(PrivacySubjectEraserRegistry::class);
    app()->singleton(PrivacySubjectExporterRegistry::class);

    $provider = new FormBuilderServiceProvider(app());
    $registerContributors = new ReflectionMethod($provider, 'registerPrivacyCenterContributors');
    $registerContributors->invoke($provider);

    $subject = new FormBuilderPrivacyTestSubject([
        'id' => 123,
        'email' => 'privacy@example.test',
    ]);
    $matchingSubmission = Submission::factory()->create([
        'payload' => new SubmissionPayloadData(['email' => 'privacy@example.test', 'message' => 'Private detail']),
        'meta' => new SubmissionMetaData(ipAddress: '203.0.113.10'),
    ]);
    $otherSubmission = Submission::factory()->create([
        'payload' => new SubmissionPayloadData(['email' => 'other@example.test']),
    ]);

    $export = app(PrivacySubjectExporterRegistry::class)->export($subject);
    $affectedRecords = app(PrivacySubjectEraserRegistry::class)->anonymize($subject);

    expect($export['form-builder']['submissions'])->toHaveCount(1)
        ->and(data_get($export, 'form-builder.submissions.0.payload.values.message'))->toBe('Private detail')
        ->and($affectedRecords)->toBe(1)
        ->and(Submission::query()->whereKey($matchingSubmission->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($otherSubmission->getKey())->exists())->toBeTrue();
});

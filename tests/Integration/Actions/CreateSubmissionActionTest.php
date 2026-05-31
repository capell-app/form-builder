<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

it('validates and stores a submission', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'validation_rules' => ['email'],
            ],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com'],
        meta: new SubmissionMetaData(ipAddress: '127.0.0.1', userAgent: 'Pest'),
    );

    expect($submission->exists)->toBeTrue()
        ->and($submission->form->is($form))->toBeTrue()
        ->and($submission->site_id)->toBe($form->site_id)
        ->and($submission->payload->values)->toBe(['email' => 'ben@example.com'])
        ->and($submission->status)->toBe(SubmissionStatus::New);

    Event::assertDispatched(FormSubmitted::class);
});

it('does not store honeypot values in payload', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
            ['key' => 'company_website', 'label' => 'Company website', 'type' => 'honeypot', 'required' => false],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com', 'company_website' => null],
        meta: new SubmissionMetaData,
    );

    expect($submission->payload->values)->toBe(['email' => 'ben@example.com']);
});

it('stores triggered honeypot submissions as spam without dispatching submission events', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
            ['key' => 'company_website', 'label' => 'Company website', 'type' => 'honeypot', 'required' => false],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'bot@example.com', 'company_website' => 'https://spam.example'],
        meta: new SubmissionMetaData(ipAddress: '127.0.0.1', userAgent: 'Bot'),
    );

    expect($submission->status)->toBe(SubmissionStatus::Spam)
        ->and($submission->payload->values)->toBe([]);

    Event::assertNotDispatched(FormSubmitted::class);
});

it('throws a validation exception for invalid data', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'not-an-email'],
        meta: new SubmissionMetaData,
    );
})->throws(ValidationException::class);

it('stores valid submissions when notification queueing fails', function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new RuntimeException('SMTP unavailable'));

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'notification_email' => 'hello@capell.app',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com'],
        meta: new SubmissionMetaData,
    );

    expect($submission->exists)->toBeTrue()
        ->and(Submission::query()->whereKey($submission->getKey())->exists())->toBeTrue();
});

it('does not validate or store fields hidden by conditional logic', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'interest',
                'label' => 'Interest',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'sales' => 'Sales',
                    'support' => 'Support',
                ],
            ],
            [
                'key' => 'support_message',
                'label' => 'Support message',
                'type' => 'textarea',
                'required' => true,
                'visibility_conditions' => [
                    [
                        'field_key' => 'interest',
                        'operator' => 'equals',
                        'value' => 'support',
                    ],
                ],
            ],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: [
            'interest' => 'sales',
            'support_message' => 'This should be ignored.',
        ],
        meta: new SubmissionMetaData,
    );

    expect($submission->payload->values)->toBe([
        'interest' => 'sales',
    ]);
});

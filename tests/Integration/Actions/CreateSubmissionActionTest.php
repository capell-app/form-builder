<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildSubmissionsCsvAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\RedactSubmissionWebhookErrorMessageAction;
use Capell\FormBuilder\Actions\SendSubmissionNotificationAction;
use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Tests\Fixtures\StaticFormBuilderWebhookHostResolver;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app()->instance(FormBuilderWebhookHostResolver::class, new StaticFormBuilderWebhookHostResolver([
        'hooks.example.test' => ['93.184.216.34'],
    ]));
});

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

    Event::assertDispatched(
        FormSubmitted::class,
        fn (FormSubmitted $event): bool => $event->submission?->is($submission)
            && $event->payload === ['email' => 'ben@example.com']
            && $event->submissionData->stored
            && $event->submissionData->submissionId === $submission->getKey()
            && $event->submissionData->payload->values === ['email' => 'ben@example.com']
            && $event->submissionData->metadata->ipAddress === '127.0.0.1',
    );
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
        ->and($submission->payload->values)->toBe([])
        ->and($submission->meta->spamScore)->toBe(100)
        ->and($submission->meta->spamReasons)->toContain('honeypot');

    Event::assertNotDispatched(FormSubmitted::class);
});

it('stores scored spam submissions without dispatching submission events', function (): void {
    Event::fake([FormSubmitted::class]);
    config()->set('capell-form-builder.spam_scoring.max_links', 0);
    config()->set('capell-form-builder.spam_scoring.blocked_keywords', ['rank-fast']);

    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: [
            'email' => 'person@example.com',
            'message' => 'rank-fast https://one.example https://two.example https://three.example https://four.example',
        ],
        meta: new SubmissionMetaData(userAgent: 'Pest'),
    );

    expect($submission->status)->toBe(SubmissionStatus::Spam)
        ->and($submission->payload->values)->toBe([
            'email' => 'person@example.com',
            'message' => 'rank-fast https://one.example https://two.example https://three.example https://four.example',
        ])
        ->and($submission->meta->spamScore)->toBeGreaterThanOrEqual(75)
        ->and($submission->meta->spamReasons)->toContain('too_many_links');

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

it('does not send notifications for spam submissions when called directly', function (): void {
    Mail::fake();

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'notification_email' => 'hello@capell.app',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['email' => 'attacker@example.com']],
        'status' => SubmissionStatus::Spam,
    ]);

    SendSubmissionNotificationAction::run($submission);

    Mail::assertNotQueued(FormSubmissionNotificationMail::class);
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

it('exports stored submissions to csv', function (): void {
    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    Submission::factory()->for($form)->create([
        'payload' => ['values' => ['email' => 'ben@example.com', 'message' => 'Hello']],
    ]);

    $csv = BuildSubmissionsCsvAction::run($form);

    expect($csv)->toContain('submission_id,form_id,form_name,site_id,status,submitted_at,email,message')
        ->and($csv)->toContain('ben@example.com,Hello');
});

it('dispatches configured submission webhooks after successful stored submissions', function (): void {
    /** @var array<string, mixed>|null $requestOptions */
    $requestOptions = null;

    Http::fake(function (Request $request, array $options) use (&$requestOptions): PromiseInterface {
        $requestOptions = $options;

        return Http::response(['ok' => true]);
    });

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'webhook_url' => 'https://hooks.example.test/form',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com'],
        meta: new SubmissionMetaData(url: 'https://example.test/contact'),
    );

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.test/form'
        && $request['event'] === 'form.submitted'
        && $request->hasHeader('Host', 'hooks.example.test')
        && $request['form']['handle'] === $form->handle
        && $request['submission']['payload']['email'] === 'ben@example.com');

    expect(data_get($requestOptions, 'allow_redirects'))->toBeFalse()
        ->and(data_get($requestOptions, 'curl.' . CURLOPT_RESOLVE))->toBe(['hooks.example.test:443:93.184.216.34']);
});

it('blocks configured submission webhooks to private hosts', function (): void {
    Http::fake();

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'webhook_url' => 'https://127.0.0.1/form',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    $submission = CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com'],
        meta: new SubmissionMetaData(url: 'https://example.test/contact'),
    );

    expect($submission->exists)->toBeTrue();

    Http::assertNothingSent();
});

it('blocks plaintext configured submission webhooks by default', function (): void {
    Http::fake();

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'webhook_url' => 'http://hooks.example.test/form',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);

    CreateSubmissionAction::run(
        form: $form,
        input: ['email' => 'ben@example.com'],
        meta: new SubmissionMetaData(url: 'https://example.test/contact'),
    );

    Http::assertNothingSent();
});

it('keeps stored submissions when configured webhooks fail', function (): void {
    Http::fake([
        'https://hooks.example.test/form' => Http::response([], 500),
    ]);

    $form = Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'webhook_url' => 'https://hooks.example.test/form',
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

it('redacts secret-like values from submission webhook error messages', function (): void {
    $message = RedactSubmissionWebhookErrorMessageAction::run(
        'Authorization: Bearer bearer-token-secret api_key=form-webhook-secret token=query-token-secret',
        'https://hooks.example.test/form?api_key=form-webhook-secret&token=query-token-secret',
    );

    expect($message)->toContain('Authorization: Bearer [redacted]')
        ->and($message)->toContain('api_key=[redacted]')
        ->and($message)->toContain('token=[redacted]')
        ->and($message)->not->toContain('bearer-token-secret')
        ->and($message)->not->toContain('form-webhook-secret')
        ->and($message)->not->toContain('query-token-secret');
});

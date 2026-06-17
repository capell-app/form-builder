<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction;
use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Support\SpamProtection\TurnstileSpamProtectionProvider;
use Illuminate\Support\Facades\Http;

it('scores honeypots excessive links blocked keywords and repeated values', function (): void {
    config()->set('capell-form-builder.spam_scoring.max_links', 1);
    config()->set('capell-form-builder.spam_scoring.blocked_keywords', ['rank-fast']);

    $form = Form::factory()->make([
        'schema' => [
            ['key' => 'company_website', 'label' => 'Company website', 'type' => 'honeypot'],
        ],
    ]);

    $score = CalculateSubmissionSpamScoreAction::run(
        form: $form,
        input: [
            'company_website' => 'https://spam.example',
            'message' => 'rank-fast https://a.example https://b.example Same content Same content Same content',
        ],
        meta: new SubmissionMetaData,
    );

    expect($score->score)->toBe(100)
        ->and($score->reasons)->toContain('honeypot')
        ->and($score->reasons)->toContain('too_many_links')
        ->and($score->reasons)->toContain('blocked_keyword:rank-fast')
        ->and($score->reasons)->toContain('missing_user_agent');
});

it('returns a clean score when scoring is disabled', function (): void {
    config()->set('capell-form-builder.spam_scoring.enabled', false);

    $score = CalculateSubmissionSpamScoreAction::run(
        form: Form::factory()->make(),
        input: ['message' => 'https://a.example https://b.example'],
        meta: new SubmissionMetaData,
    );

    expect($score->score)->toBe(0)
        ->and($score->reasons)->toBe([]);
});

it('marks submissions as spam when the configured protection provider rejects them', function (): void {
    config()->set('capell-form-builder.spam_protection.enabled', true);
    app()->instance(SpamProtectionProvider::class, new class implements SpamProtectionProvider
    {
        public function key(): string
        {
            return 'fixture';
        }

        public function verify(Form $form, array $input, SubmissionMetaData $meta): bool
        {
            return false;
        }
    });

    $score = CalculateSubmissionSpamScoreAction::run(
        form: Form::factory()->make(),
        input: ['message' => 'Legitimate looking message'],
        meta: new SubmissionMetaData(userAgent: 'Feature test browser'),
    );

    expect($score->score)->toBe(100)
        ->and($score->reasons)->toContain('spam_provider:fixture_failed');
});

it('verifies turnstile tokens through cloudflare siteverify', function (): void {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
    ]);
    config()->set('capell-form-builder.spam_protection.turnstile.secret_key', 'turnstile-secret');

    $verified = (new TurnstileSpamProtectionProvider)->verify(
        form: Form::factory()->make(),
        input: ['cf-turnstile-response' => 'turnstile-token'],
        meta: new SubmissionMetaData(ipAddress: '203.0.113.10'),
    );

    expect($verified)->toBeTrue();

    Http::assertSent(static fn ($request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'turnstile-secret'
        && $request['response'] === 'turnstile-token'
        && $request['remoteip'] === '203.0.113.10');
});

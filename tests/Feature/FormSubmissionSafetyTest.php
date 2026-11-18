<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildSubmissionsCsvAction;
use Capell\FormBuilder\Actions\DeleteSubmissionUploadsAction;
use Capell\FormBuilder\Actions\ResolveSafeSuccessRedirectUrlAction;
use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SpamProtection\TurnstileSpamProtectionProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

function enableFormBuilderTurnstile(): void
{
    config()->set('capell-form-builder.spam_protection.enabled', true);
    config()->set('capell-form-builder.spam_protection.provider', TurnstileSpamProtectionProvider::class);
    config()->set('capell-form-builder.spam_protection.turnstile.site_key', 'site-key-123');
    config()->set('capell-form-builder.spam_protection.turnstile.secret_key', 'secret');
    app()->forgetInstance(SpamProtectionProvider::class);
}

function turnstileForm(): Form
{
    $form = Form::factory()->create([
        'handle' => 'turnstile-form',
        'schema' => [['key' => 'email', 'label' => 'Email', 'type' => FormFieldType::Email->value, 'required' => true]],
    ]);
    bindFormBuilderFrontendSite($form->site);

    return $form;
}

it('renders the Turnstile widget with the configured site key when Turnstile is enabled', function (): void {
    enableFormBuilderTurnstile();
    turnstileForm();

    livewire(FormComponent::class, ['handle' => 'turnstile-form'])
        ->assertSee('data-turnstile-widget', false)
        ->assertSee('site-key-123', false)
        ->assertDontSee('secret', false);
});

it('does not render the Turnstile widget when spam protection is off', function (): void {
    turnstileForm();

    livewire(FormComponent::class, ['handle' => 'turnstile-form'])
        ->assertDontSee('data-turnstile-widget', false);
});

it('accepts a genuine submission carrying a verified Turnstile token', function (): void {
    enableFormBuilderTurnstile();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    turnstileForm();

    livewire(FormComponent::class, ['handle' => 'turnstile-form'])
        ->set('data.email', 'ben@example.com')
        ->set('data.cf-turnstile-response', 'good-token')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(Submission::query()->firstOrFail()->status)->toBe(SubmissionStatus::New);
});

it('marks a submission without a Turnstile token as spam', function (): void {
    enableFormBuilderTurnstile();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    turnstileForm();

    livewire(FormComponent::class, ['handle' => 'turnstile-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit');

    expect(Submission::query()->firstOrFail()->status)->toBe(SubmissionStatus::Spam);
});

it('still marks a token-less submission as spam when heuristic scoring is disabled', function (): void {
    config()->set('capell-form-builder.spam_scoring.enabled', false);
    enableFormBuilderTurnstile();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    turnstileForm();

    livewire(FormComponent::class, ['handle' => 'turnstile-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit');

    expect(Submission::query()->firstOrFail()->status)->toBe(SubmissionStatus::Spam);
});

it('writes no upload to storage for a submission the spam gate rejects', function (): void {
    Storage::fake('form-builder-test');
    config()->set('capell-form-builder.uploads.disk', 'form-builder-test');
    enableFormBuilderTurnstile();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $form = Form::factory()->create([
        'handle' => 'spam-upload',
        'schema' => [['key' => 'brief', 'label' => 'Brief', 'type' => FormFieldType::File->value, 'accepted_file_types' => ['pdf']]],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'spam-upload'])
        ->set('data.brief', UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->call('submit');

    expect(Submission::query()->firstOrFail()->status)->toBe(SubmissionStatus::Spam)
        ->and(Storage::disk('form-builder-test')->allFiles())->toBe([]);
});

it('removes stored uploads when a form is deleted, but never files outside the upload directory', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('form-builder/submissions/a.pdf', 'a');
    Storage::disk('local')->put('elsewhere/keep.txt', 'keep');
    $form = Form::factory()->create();
    Submission::factory()->for($form)->create([
        'payload' => new SubmissionPayloadData([
            'one' => ['disk' => 'local', 'path' => 'form-builder/submissions/a.pdf'],
            'two' => ['disk' => 'local', 'path' => 'elsewhere/keep.txt'],
        ]),
        'meta' => new SubmissionMetaData,
    ]);

    $form->delete();

    Storage::disk('local')->assertMissing('form-builder/submissions/a.pdf');
    Storage::disk('local')->assertExists('elsewhere/keep.txt');
    expect(Submission::query()->count())->toBe(0);
});

it('deletes uploads for the given submissions', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('form-builder/submissions/b.pdf', 'b');
    $submission = Submission::factory()->for(Form::factory()->create())->create([
        'payload' => new SubmissionPayloadData(['f' => ['disk' => 'local', 'path' => 'form-builder/submissions/b.pdf']]),
        'meta' => new SubmissionMetaData,
    ]);

    expect(DeleteSubmissionUploadsAction::run(Submission::query()->whereKey($submission->getKey())))->toBe(1);
    Storage::disk('local')->assertMissing('form-builder/submissions/b.pdf');
});

it('only allows relative or https success redirects', function (string $url, ?string $expected): void {
    expect(ResolveSafeSuccessRedirectUrlAction::run($url))->toBe($expected);
})->with([
    ['/thanks', '/thanks'],
    ['https://example.com/thanks', 'https://example.com/thanks'],
    ['javascript:alert(1)', null],
    ['//evil.example', null],
    ['http://example.com', null],
    ['data:text/html,x', null],
    ['/\\evil.example', null],
    ['', null],
    'credentials in the authority' => ['https://example.com@evil.example/thanks', null],
    'smuggled tab inside an https URL' => ["https://example.com/\tthanks", null],
]);

it('excludes spam from the CSV export', function (): void {
    $form = Form::factory()->create();
    Submission::factory()->for($form)->create(['status' => SubmissionStatus::New, 'payload' => new SubmissionPayloadData(['note' => 'genuine-entry']), 'meta' => new SubmissionMetaData]);
    Submission::factory()->for($form)->create(['status' => SubmissionStatus::Spam, 'payload' => new SubmissionPayloadData(['note' => 'spam-entry']), 'meta' => new SubmissionMetaData]);

    $csv = BuildSubmissionsCsvAction::run();

    expect($csv)->toContain('genuine-entry')->not->toContain('spam-entry');
});

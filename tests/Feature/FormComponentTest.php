<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\State\FrontendState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Livewire\livewire;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

it('keeps public form workflow buttons at the primary touch target size', function (): void {
    $formView = file_get_contents(dirname(__DIR__, 2) . '/resources/views/livewire/form.blade.php');

    if (! is_string($formView)) {
        throw new RuntimeException('Unable to read the public Form Builder component.');
    }

    expect(substr_count($formView, 'inline-flex min-h-11 items-center'))->toBe(3)
        ->and($formView)->not->toContain('inline-flex min-h-10 items-center');
});

it('renders and stores a submitted form', function (): void {
    resolve(RecordExtensionRenderContributionAction::class)->clear();

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->assertSee('Email')
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    $submission = Submission::query()->firstOrFail();
    $payload = formComponentSubmissionPayload($submission);
    $meta = formComponentSubmissionMeta($submission);

    expect($submission->form_id)->toBe($form->getKey())
        ->and($payload->values)->toBe(['email' => 'ben@example.com'])
        ->and($meta->url)->toBeString();

    $contribution = collect(resolve(RecordExtensionRenderContributionAction::class)->recorded())
        ->first(fn (mixed $record): bool => $record->contributionClass === FormComponent::class);

    expect($contribution?->cacheable)->toBeFalse();
});

it('rate limits repeated public form submissions for the same form, email, and ip', function (): void {
    config()->set('capell-form-builder.throttle.max_attempts', 2);
    config()->set('capell-form-builder.throttle.decay_seconds', 60);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'limited-lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    foreach (range(1, 2) as $attempt) {
        livewire(FormComponent::class, ['handle' => 'limited-lead-form'])
            ->set('data.email', 'ben@example.com')
            ->call('submit')
            ->assertSet('submitted', true);
    }

    livewire(FormComponent::class, ['handle' => 'limited-lead-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertHasErrors(['data'])
        ->assertSet('submitted', false);

    expect(Submission::query()->count())->toBe(2);
});

it('submits after hydration without a frontend site context', function (): void {
    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    $component = livewire(FormComponent::class, ['handle' => 'lead-form']);

    app()->instance(FrontendContextReader::class, new FrontendState);
    Frontend::clearResolvedInstance(FrontendContextReader::class);

    $component
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(Submission::query()->first()?->form_id)->toBe($form->getKey());
});

it('does not render inactive public forms', function (): void {
    $form = Form::factory()->create([
        'name' => 'Inactive form',
        'handle' => 'inactive-form',
        'is_active' => false,
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'inactive-form'])
        ->assertDontSee('Inactive form')
        ->call('submit')
        ->assertSet('submitted', false);

    expect(Submission::query()->count())->toBe(0);
});

it('does not resolve public forms without a frontend site context', function (): void {
    Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
    ]);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->assertDontSee('Lead form')
        ->call('submit')
        ->assertSet('submitted', false);

    expect(Submission::query()->count())->toBe(0);
});

it('scopes public form lookup to the current frontend site', function (): void {
    $currentSite = Site::factory()->withTranslations()->create();
    $otherSite = Site::factory()->withTranslations()->create();
    bindFormBuilderFrontendSite($currentSite);

    Form::factory()->for($otherSite, 'site')->create([
        'name' => 'Other site form',
        'handle' => 'shared-handle',
    ]);

    livewire(FormComponent::class, ['handle' => 'shared-handle'])
        ->assertDontSee('Other site form')
        ->call('submit')
        ->assertSet('submitted', false);

    expect(Submission::query()->count())->toBe(0);
});

it('rejects a public form reference replayed under another frontend site', function (): void {
    $currentSite = Site::factory()->withTranslations()->create();
    $otherSite = Site::factory()->withTranslations()->create();
    $form = Form::factory()->for($otherSite, 'site')->create([
        'name' => 'Other site form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);

    bindFormBuilderFrontendSite($currentSite);

    $formReference = Crypt::encryptString(json_encode([
        'form_id' => $form->getKey(),
        'site_id' => $otherSite->getKey(),
    ], JSON_THROW_ON_ERROR));

    livewire(FormComponent::class, ['formReference' => $formReference])
        ->assertDontSee('Other site form')
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', false);

    expect(Submission::query()->count())->toBe(0);
});

it('renders accessible public form states', function (): void {
    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
                'help_text' => 'Use your work email.',
            ],
            [
                'key' => 'terms',
                'label' => 'Terms',
                'type' => FormFieldType::Checkbox->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    $emailFieldId = 'capell-form-accessible-lead-email';

    livewire(FormComponent::class, ['handle' => 'lead-form', 'instanceId' => 'accessible-lead'])
        ->assertElementExists('button', fn (AssertElement $button): BaseAssert => $button->has('wire:loading.attr', 'disabled'))
        ->assertElementExists(fn (AssertElement $body): BaseAssert => $body->doesntContain('#capell-form-' . $form->getKey() . '-email'))
        ->assertElementExists('input[required]')
        ->set('data.terms', false)
        ->call('submit')
        ->assertHasErrors([
            'data.email' => 'required',
            'data.terms' => 'accepted',
        ])
        ->assertElementExists('input[aria-describedby="' . $emailFieldId . '-help ' . $emailFieldId . '-error"][aria-invalid="true"]')
        ->assertElementExists('[role="alert"]')
        ->assertSee(__('capell-form-builder::form.errors_heading'))
        ->assertElementExists('a[href="#' . $emailFieldId . '"]')
        ->set('data.email', 'ben@example.com')
        ->set('data.terms', true)
        ->call('submit')
        ->assertSet('submitted', true)
        ->assertElementExists('[role="status"]')
        ->assertElementExists('[x-init]', fn (AssertElement $element): BaseAssert => $element->has('x-init', '$nextTick(() => $el.focus())'));
});

it('renders multi-step forms with step navigation and validates before advancing', function (): void {
    $form = Form::factory()->create([
        'name' => 'Project enquiry',
        'handle' => 'project-enquiry',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
                'step_key' => 'Contact',
            ],
            [
                'key' => 'budget',
                'label' => 'Budget',
                'type' => FormFieldType::Number->value,
                'required' => true,
                'step_key' => 'Project',
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'project-enquiry', 'instanceId' => 'project-enquiry'])
        ->assertSee(__('capell-form-builder::form.step_progress', ['current' => 1, 'total' => 2]))
        ->assertSee('Contact')
        ->assertSee('Email')
        ->assertDontSee('Budget')
        ->call('nextStep')
        ->assertHasErrors(['data.email' => 'required'])
        ->assertSet('currentStepKey', 'contact')
        ->set('data.email', 'ben@example.com')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStepKey', 'project')
        ->assertSee(__('capell-form-builder::form.step_progress', ['current' => 2, 'total' => 2]))
        ->assertSee('Budget')
        ->assertDontSee('Email')
        ->set('data.budget', 5000)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(formComponentSubmissionPayload(Submission::query()->firstOrFail())->values)->toBe([
        'email' => 'ben@example.com',
        'budget' => 5000,
    ]);
});

it('renders a form element component from widget data for the current frontend site', function (): void {
    resolve(RecordExtensionRenderContributionAction::class)->clear();

    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormElementComponent::class, ['widgetData' => ['form_handle' => 'contact', 'instance_id' => 'contact-block']])
        ->assertSee('Contact')
        ->assertSee('Email')
        ->assertElementExists('#capell-form-contact-block-email');

    $contribution = collect(resolve(RecordExtensionRenderContributionAction::class)->recorded())
        ->first(fn (mixed $record): bool => $record->contributionClass === FormElementComponent::class);

    expect($contribution?->cacheable)->toBeFalse();
});

it('renders a safe fallback when a public form handle is unavailable', function (): void {
    $site = Site::factory()->withTranslations()->create();
    bindFormBuilderFrontendSite($site);

    livewire('public-form', [
        'handle' => 'missing-form',
        'widgetData' => [
            'instance_id' => 'missing-form',
            'fallback_message' => 'Send the brief by email instead.',
            'fallback_label' => 'Email the team',
            'fallback_url' => 'mailto:hello@example.test',
        ],
    ])
        ->assertSee('Send the brief by email instead.')
        ->assertSee('Email the team')
        ->assertSeeHtml('href="mailto:hello@example.test"');
});

it('uses unique child form keys for repeated form elements', function (): void {
    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormElementComponent::class, ['widgetData' => ['form_handle' => 'contact', 'instance_id' => 'first-contact-block']])
        ->assertElementExists('#capell-form-first-contact-block-email');

    livewire(FormElementComponent::class, ['widgetData' => ['form_handle' => 'contact', 'instance_id' => 'second-contact-block']])
        ->assertElementExists('#capell-form-second-contact-block-email');
});

it('stores submissions and sends notification mail when configured', function (): void {
    Mail::fake();

    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'settings' => [
            'store_submissions' => true,
            'notification_email' => 'hello@capell.app',
        ],
        'schema' => [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => FormFieldType::Text->value,
                'required' => true,
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
            [
                'key' => 'message',
                'label' => 'Message',
                'type' => FormFieldType::Textarea->value,
                'required' => true,
            ],
            [
                'key' => 'newsletter',
                'label' => 'Newsletter',
                'type' => FormFieldType::Checkbox->value,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'contact'])
        ->set('data.name', 'Ben Johnson')
        ->set('data.email', 'ben@example.com')
        ->set('data.message', 'Can you help with a Capell migration?')
        ->set('data.newsletter', true)
        ->call('submit')
        ->assertSet('submitted', true);

    $submission = Submission::query()->firstOrFail();
    $payload = formComponentSubmissionPayload($submission);

    expect($payload->values)->toBe([
        'name' => 'Ben Johnson',
        'email' => 'ben@example.com',
        'message' => 'Can you help with a Capell migration?',
        'newsletter' => true,
    ]);

    Mail::assertQueued(
        FormSubmissionNotificationMail::class,
        fn (FormSubmissionNotificationMail $mail): bool => $mail->hasTo('hello@capell.app')
            && $mail->hasReplyTo('ben@example.com'),
    );

    $mail = new FormSubmissionNotificationMail($submission->load('form'), 'ben@example.com');
    $renderedMail = $mail->render();

    expect($renderedMail)
        ->toContain('Name')
        ->toContain('Ben Johnson')
        ->toContain('Email')
        ->toContain('ben@example.com')
        ->toContain('Message')
        ->toContain('Can you help with a Capell migration?')
        ->toContain('Newsletter')
        ->toContain(__('capell-form-builder::generic.boolean.yes'));
});

it('queues submitter autoresponder mail when configured', function (): void {
    Mail::fake();

    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'autoresponder-contact',
        'settings' => [
            'store_submissions' => true,
            'autoresponder_subject' => 'We received your enquiry',
            'autoresponder_body' => "Thanks for getting in touch.\nWe will reply soon.",
        ],
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'autoresponder-contact'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    Mail::assertQueued(
        FormSubmissionAutoresponderMail::class,
        fn (FormSubmissionAutoresponderMail $mail): bool => $mail->hasTo('ben@example.com')
            && $mail->subjectLine === 'We received your enquiry'
            && $mail->messageBody === "Thanks for getting in touch.\nWe will reply soon.",
    );
});

it('redirects after successful public submission when configured', function (): void {
    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'redirect-contact',
        'settings' => [
            'store_submissions' => true,
            'success_redirect_url' => '/thanks',
        ],
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'redirect-contact'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertRedirect('/thanks');
});

it('stores file upload metadata through the Livewire form path', function (): void {
    Storage::fake('form-builder-test');
    config()->set('capell-form-builder.uploads.disk', 'form-builder-test');

    $form = Form::factory()->create([
        'name' => 'Upload form',
        'handle' => 'upload-form',
        'schema' => [
            [
                'key' => 'brief',
                'label' => 'Brief',
                'type' => FormFieldType::File->value,
                'required' => true,
                'accepted_file_types' => ['pdf'],
                'max_file_size_kilobytes' => 128,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'upload-form'])
        ->set('data.brief', UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $payload = formComponentSubmissionPayload(Submission::query()->firstOrFail());

    $fileReference = formComponentStoredFileReference($payload, 'brief');

    expect($fileReference['original_name'])->toBe('brief.pdf')
        ->and($fileReference['mime_type'])->toBeString()
        ->and($fileReference['size'])->toBeInt()
        ->and($fileReference['disk'])->toBe('form-builder-test')
        ->and($fileReference['path'])->toBeString();

    Storage::disk('form-builder-test')->assertExists($fileReference['path']);
});

it('stores payment field values through the Livewire form path', function (): void {
    $form = Form::factory()->create([
        'name' => 'Payment form',
        'handle' => 'payment-form',
        'schema' => [
            [
                'key' => 'amount_cents',
                'label' => 'Amount',
                'type' => FormFieldType::Payment->value,
                'required' => true,
                'payment_amount_cents' => 2500,
                'payment_currency' => 'GBP',
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'payment-form'])
        ->assertSee(__('capell-form-builder::form.continue_to_payment'))
        ->assertSee(__('capell-form-builder::form.payment_fixed_amount', ['amount' => '25.00', 'currency' => 'GBP']))
        ->set('data.amount_cents', 2500)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(formComponentSubmissionPayload(Submission::query()->firstOrFail())->values)->toBe([
        'amount_cents' => 2500,
    ]);
});

it('redirects payment form submissions to a signed Payments checkout URL when Payments is installed', function (): void {
    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');

    Route::get('capell/payments/forms/{submission}/checkout', static fn (): string => '')
        ->middleware('signed')
        ->name('capell-payments.form-builder.checkout');

    $form = Form::factory()->create([
        'name' => 'Payment form',
        'handle' => 'payment-checkout-form',
        'schema' => [
            [
                'key' => 'amount_cents',
                'label' => 'Amount',
                'type' => FormFieldType::Payment->value,
                'required' => true,
                'payment_amount_cents' => 2500,
                'payment_currency' => 'GBP',
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'payment-checkout-form'])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertRedirect();

    $submission = Submission::query()->firstOrFail();

    expect(formComponentSubmissionPayload($submission)->values)->toBe([
        'amount_cents' => 2500,
    ]);
});

it('calculates and stores calculation fields through the Livewire form path', function (): void {
    $form = Form::factory()->create([
        'name' => 'Quote form',
        'handle' => 'quote-form',
        'schema' => [
            [
                'key' => 'quantity',
                'label' => 'Quantity',
                'type' => FormFieldType::Number->value,
                'required' => true,
            ],
            [
                'key' => 'unit_price',
                'label' => 'Unit price',
                'type' => FormFieldType::Number->value,
                'required' => true,
            ],
            [
                'key' => 'total',
                'label' => 'Total',
                'type' => FormFieldType::Calculation->value,
                'required' => true,
                'calculation_expression' => 'quantity * unit_price',
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'quote-form'])
        ->set('data.quantity', 3)
        ->set('data.unit_price', 120)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(formComponentSubmissionPayload(Submission::query()->firstOrFail())->values)->toBe([
        'quantity' => 3,
        'unit_price' => 120,
        'total' => 360,
    ]);
});

it('dispatches submitted payloads when submissions are not stored', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'settings' => [
            'store_submissions' => false,
        ],
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    Event::assertDispatched(
        FormSubmitted::class,
        fn (FormSubmitted $event): bool => $event->submission === null
            && $event->payload === ['email' => 'ben@example.com']
            && ! $event->submissionData->stored
            && $event->submissionData->payload->values === ['email' => 'ben@example.com'],
    );

    expect(Submission::query()->count())->toBe(0);
});

it('uses full schema validation when submissions are not stored', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'settings' => [
            'store_submissions' => false,
        ],
        'schema' => [
            [
                'key' => 'interest',
                'label' => 'Interest',
                'type' => FormFieldType::Select->value,
                'required' => true,
                'options' => [
                    'migration' => 'Migration',
                    'support' => 'Support',
                ],
            ],
            [
                'key' => 'terms',
                'label' => 'Terms',
                'type' => FormFieldType::Checkbox->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.interest', 'not-an-option')
        ->set('data.terms', false)
        ->call('submit')
        ->assertHasErrors([
            'data.interest' => 'in',
            'data.terms' => 'accepted',
        ])
        ->assertSet('submitted', false);

    Event::assertNotDispatched(FormSubmitted::class);
});

it('renders and validates only fields visible under conditional logic', function (): void {
    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'conditional-lead-form',
        'schema' => [
            [
                'key' => 'interest',
                'label' => 'Interest',
                'type' => FormFieldType::Select->value,
                'required' => true,
                'options' => [
                    'sales' => 'Sales',
                    'support' => 'Support',
                ],
            ],
            [
                'key' => 'support_message',
                'label' => 'Support message',
                'type' => FormFieldType::Textarea->value,
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
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'conditional-lead-form'])
        ->assertSee('Interest')
        ->assertDontSee('Support message')
        ->set('data.interest', 'sales')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(formComponentSubmissionPayload(Submission::query()->firstOrFail())->values)->toBe([
        'interest' => 'sales',
    ]);

    livewire(FormComponent::class, ['handle' => 'conditional-lead-form'])
        ->set('data.interest', 'support')
        ->assertSee('Support message')
        ->call('submit')
        ->assertHasErrors([
            'data.support_message' => 'required',
        ]);
});

it('honours public metadata collection settings', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'settings' => [
            'store_submissions' => false,
            'collect_ip_address' => false,
            'collect_user_agent' => false,
        ],
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    Event::assertDispatched(
        FormSubmitted::class,
        function (FormSubmitted $event): bool {
            $metadata = $event->metadata;

            return $metadata instanceof SubmissionMetaData
                && $metadata->ipAddress === null
                && $metadata->userAgent === null;
        },
    );
});

it('records honeypot submissions as spam through the Livewire form', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
            [
                'key' => 'company_website',
                'label' => 'Company website',
                'type' => FormFieldType::Honeypot->value,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.email', 'bot@example.com')
        ->set('data.company_website', 'https://spam.example')
        ->call('submit')
        ->assertSet('submitted', true);

    $submission = Submission::query()->firstOrFail();
    $payload = formComponentSubmissionPayload($submission);

    expect($submission->form_id)->toBe($form->getKey())
        ->and($submission->status)->toBe(SubmissionStatus::Spam)
        ->and($payload->values)->toBe([]);

    Event::assertNotDispatched(FormSubmitted::class);
});

it('records honeypot submissions as spam before validating public fields', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
            [
                'key' => 'company_website',
                'label' => 'Company website',
                'type' => FormFieldType::Honeypot->value,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.company_website', 'https://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $submission = Submission::query()->firstOrFail();
    $payload = formComponentSubmissionPayload($submission);

    expect($submission->form_id)->toBe($form->getKey())
        ->and($submission->status)->toBe(SubmissionStatus::Spam)
        ->and($payload->values)->toBe([]);

    Event::assertNotDispatched(FormSubmitted::class);
});

it('silently swallows honeypot submissions when submissions are not stored', function (): void {
    Event::fake([FormSubmitted::class]);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'settings' => [
            'store_submissions' => false,
        ],
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
            [
                'key' => 'company_website',
                'label' => 'Company website',
                'type' => FormFieldType::Honeypot->value,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.email', 'bot@example.com')
        ->set('data.company_website', 'https://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    Event::assertNotDispatched(FormSubmitted::class);

    expect(Submission::query()->count())->toBe(0);
});

it('throttles repeated public form submissions for the configured email field key and ip address', function (): void {
    config()->set('capell-form-builder.throttle.max_attempts', 2);
    config()->set('capell-form-builder.throttle.decay_seconds', 60);

    $form = Form::factory()->create([
        'name' => 'Lead form',
        'handle' => 'lead-form',
        'schema' => [
            [
                'key' => 'contact_email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    bindFormBuilderFrontendSite($form->site);

    foreach (range(1, 2) as $attempt) {
        livewire(FormComponent::class, ['handle' => 'lead-form'])
            ->set('data.contact_email', 'ben@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);
    }

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.contact_email', 'ben@example.com')
        ->call('submit')
        ->assertHasErrors(['data'])
        ->assertSee(__('capell-form-builder::message.too_many_submissions'));

    expect(Submission::query()->count())->toBe(2);
});

function bindFormBuilderFrontendSite(Site $site): void
{
    $state = (new FrontendState)->withSite($site);

    app()->instance(FrontendContextReader::class, $state);
    Frontend::clearResolvedInstance(FrontendContextReader::class);
}

function formComponentSubmissionPayload(Submission $submission): SubmissionPayloadData
{
    $payload = $submission->payload;

    throw_unless($payload instanceof SubmissionPayloadData, RuntimeException::class, 'Expected form submission payload data to be cast.');

    return $payload;
}

function formComponentSubmissionMeta(Submission $submission): SubmissionMetaData
{
    $meta = $submission->meta;

    throw_unless($meta instanceof SubmissionMetaData, RuntimeException::class, 'Expected form submission meta data to be cast.');

    return $meta;
}

/**
 * @return array{original_name: string, mime_type: string, size: int, disk: string, path: string}
 */
function formComponentStoredFileReference(SubmissionPayloadData $payload, string $key): array
{
    $fileReference = $payload->values[$key] ?? null;

    throw_unless(is_array($fileReference), RuntimeException::class, 'Expected stored file reference array.');
    throw_unless(is_string($fileReference['original_name'] ?? null), RuntimeException::class, 'Expected stored file original name.');
    throw_unless(is_string($fileReference['mime_type'] ?? null), RuntimeException::class, 'Expected stored file MIME type.');
    throw_unless(is_int($fileReference['size'] ?? null), RuntimeException::class, 'Expected stored file size.');
    throw_unless(is_string($fileReference['disk'] ?? null), RuntimeException::class, 'Expected stored file disk.');
    throw_unless(is_string($fileReference['path'] ?? null), RuntimeException::class, 'Expected stored file path.');

    return [
        'original_name' => $fileReference['original_name'],
        'mime_type' => $fileReference['mime_type'],
        'size' => $fileReference['size'],
        'disk' => $fileReference['disk'],
        'path' => $fileReference['path'],
    ];
}

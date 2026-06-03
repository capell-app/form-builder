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
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Frontend\Support\State\FrontendState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

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

    app()->instance(CapellFrontendContext::class, new CapellFrontendContext(new FrontendState));
    Frontend::clearResolvedInstance(CapellFrontendContext::class);

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
        fn (FormSubmitted $event): bool => $event->payload === ['email' => 'ben@example.com'],
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

it('throttles repeated public form submissions for the same form email and ip address', function (): void {
    config()->set('capell-form-builder.throttle.max_attempts', 2);
    config()->set('capell-form-builder.throttle.decay_seconds', 60);

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

    foreach (range(1, 2) as $attempt) {
        livewire(FormComponent::class, ['handle' => 'lead-form'])
            ->set('data.email', 'ben@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);
    }

    livewire(FormComponent::class, ['handle' => 'lead-form'])
        ->set('data.email', 'ben@example.com')
        ->call('submit')
        ->assertHasErrors(['data'])
        ->assertSee(__('capell-form-builder::message.too_many_submissions'));

    expect(Submission::query()->count())->toBe(2);
});

function bindFormBuilderFrontendSite(Site $site): void
{
    $state = (new FrontendState)->withSite($site);

    app()->instance(CapellFrontendContext::class, new CapellFrontendContext($state));
    Frontend::clearResolvedInstance(CapellFrontendContext::class);
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

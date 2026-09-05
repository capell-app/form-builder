<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\BuildFormAgentToolManifestAction;
use Capell\FormBuilder\Actions\BuildFormComponentValidationRulesAction;
use Capell\FormBuilder\Actions\GuardFormSubmissionRateLimitAction;
use Capell\FormBuilder\Actions\ResolveFormComponentFormAction;
use Capell\FormBuilder\Actions\ResolveFormComponentStepStateAction;
use Capell\FormBuilder\Data\FormComponentStepStateData;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use Spatie\LaravelData\DataCollection;

it('builds Livewire data-prefixed validation rules for a form step', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
                'step_key' => 'contact',
            ],
            [
                'key' => 'budget',
                'label' => 'Budget',
                'type' => FormFieldType::Number->value,
                'required' => true,
                'step_key' => 'project',
            ],
        ],
    ]);

    $state = ResolveFormComponentStepStateAction::run($form, [], 'contact');

    expect($state->currentStep)->not->toBeNull()
        ->and(BuildFormComponentValidationRulesAction::run($form, [], $state->currentStep?->fields))->toBe([
            'data.email' => ['required', 'email', 'max:255'],
        ])
        ->and(BuildFormComponentValidationRulesAction::run($form))->toBe([
            'data.email' => ['required', 'email', 'max:255'],
            'data.budget' => ['required', 'numeric'],
        ]);
});

it('builds a bounded public form tool from hydrated single-step fields', function (): void {
    $steps = collect([new FormStepData(
        key: 'default',
        label: 'Form',
        fields: collect([
            new FormFieldData(
                key: 'email',
                label: 'Email',
                type: FormFieldType::Email,
                required: true,
            ),
            new FormFieldData(
                key: 'budget',
                label: 'Budget',
                type: FormFieldType::Number,
            ),
            new FormFieldData(
                key: 'interest',
                label: 'Interest',
                type: FormFieldType::Select,
                options: ['consulting' => 'Consulting', 'training' => 'Training'],
            ),
            new FormFieldData(
                key: 'consent',
                label: 'Consent',
                type: FormFieldType::Checkbox,
                required: true,
            ),
            new FormFieldData(
                key: 'campaign',
                label: 'Campaign',
                type: FormFieldType::Hidden,
            ),
        ]),
    )]);

    $manifest = BuildFormAgentToolManifestAction::run($steps, $steps->firstOrFail()->fields, 'capell-form-lead');

    if (! is_array($manifest)) {
        throw new RuntimeException('Expected a form agent manifest.');
    }

    expect($manifest)->not->toBeNull()
        ->and($manifest['messages'])->toBe([
            'confirmForm' => 'Submit this form with the following values?',
        ])
        ->and($manifest['tools'][0]['name'])->toBe('form.submit.capell-form-lead')
        ->and($manifest['tools'][0]['effect'])->toBe('write')
        ->and($manifest['tools'][0]['binding'])->toBe([
            'type' => 'form',
            'target' => 'capell-form-lead',
        ])
        ->and($manifest['tools'][0]['inputSchema'])->toBe([
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
                'budget' => ['type' => 'number'],
                'interest' => ['type' => 'string', 'enum' => ['consulting', 'training']],
                'consent' => ['type' => 'boolean'],
            ],
            'required' => ['email', 'consent'],
            'additionalProperties' => false,
        ]);
});

it('does not expose unsupported or multi-step forms as public tools', function (): void {
    $unsupported = collect([new FormStepData(
        key: 'default',
        label: 'Form',
        fields: collect([new FormFieldData(
            key: 'attachment',
            label: 'Attachment',
            type: FormFieldType::File,
        )]),
    )]);
    $multiStep = collect([
        new FormStepData(key: 'one', label: 'One', fields: collect()),
        new FormStepData(key: 'two', label: 'Two', fields: collect()),
    ]);

    expect(BuildFormAgentToolManifestAction::run($unsupported, $unsupported->firstOrFail()->fields, 'capell-form-upload'))->toBeNull()
        ->and(BuildFormAgentToolManifestAction::run($multiStep, $multiStep->firstOrFail()->fields, 'capell-form-steps'))->toBeNull();
});

it('does not expose forms when external spam protection needs an unsupported token', function (): void {
    config()->set('capell-form-builder.spam_protection.enabled', true);

    $steps = collect([new FormStepData(
        key: 'default',
        label: 'Form',
        fields: collect([new FormFieldData(
            key: 'email',
            label: 'Email',
            type: FormFieldType::Email,
        )]),
    )]);

    expect(BuildFormAgentToolManifestAction::run($steps, $steps->firstOrFail()->fields, 'capell-form-contact'))->toBeNull();
});

it('guards repeated form submissions by form email and IP address', function (): void {
    config()->set('capell-form-builder.throttle.max_attempts', 2);
    config()->set('capell-form-builder.throttle.decay_seconds', 60);

    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'required' => true,
            ],
        ],
    ]);
    $fields = formBuilderBoundaryFields($form);

    GuardFormSubmissionRateLimitAction::run($form, $fields, ['email' => 'ben@example.com'], '203.0.113.10');
    GuardFormSubmissionRateLimitAction::run($form, $fields, ['email' => 'ben@example.com'], '203.0.113.10');

    expect(function () use ($fields, $form): void {
        GuardFormSubmissionRateLimitAction::run($form, $fields, ['email' => 'ben@example.com'], '203.0.113.10');
    })
        ->toThrow(ValidationException::class);

    GuardFormSubmissionRateLimitAction::run($form, $fields, ['email' => 'other@example.com'], '203.0.113.10');
});

it('resolves form component step state and adjacent steps', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => FormFieldType::Email->value,
                'step_key' => 'contact details',
            ],
            [
                'key' => 'budget',
                'label' => 'Budget',
                'type' => FormFieldType::Number->value,
                'step_key' => 'project',
            ],
        ],
    ]);

    $contactState = ResolveFormComponentStepStateAction::run($form, [], 'missing-step');
    $projectState = ResolveFormComponentStepStateAction::run($form, [], 'project');

    expect($contactState)->toBeInstanceOf(FormComponentStepStateData::class)
        ->and($contactState->currentStepKey)->toBe('contact-details')
        ->and($contactState->currentStepIndex)->toBe(0)
        ->and($contactState->stepAfter('contact-details')?->key)->toBe('project')
        ->and($contactState->isFinalStep())->toBeFalse()
        ->and($projectState->stepBefore('project')?->key)->toBe('contact-details')
        ->and($projectState->isFinalStep())->toBeTrue();
});

it('resolves form component forms by current site handle id and reference', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $otherSite = Site::factory()->withTranslations()->create();
    $form = Form::factory()->for($site, 'site')->create([
        'handle' => 'contact',
    ]);
    Form::factory()->for($otherSite, 'site')->create([
        'handle' => 'contact',
    ]);

    $reference = ResolveFormComponentFormAction::referenceFor($form);
    $formByHandle = ResolveFormComponentFormAction::run('contact', '', $site);
    $formById = ResolveFormComponentFormAction::run((int) $form->getKey(), '', $site);
    $formByReference = ResolveFormComponentFormAction::run(null, $reference, $site);
    $otherSiteResolvedForm = ResolveFormComponentFormAction::run('contact', '', $otherSite);

    Assert::assertInstanceOf(Form::class, $formByHandle);
    Assert::assertInstanceOf(Form::class, $formById);
    Assert::assertInstanceOf(Form::class, $formByReference);
    Assert::assertInstanceOf(Form::class, $otherSiteResolvedForm);

    expect($formByHandle->is($form))->toBeTrue()
        ->and($formById->is($form))->toBeTrue()
        ->and($formByReference->is($form))->toBeTrue()
        ->and($otherSiteResolvedForm->is($form))->toBeFalse();
});

it('rejects invalid inactive and cross-site form component references', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $otherSite = Site::factory()->withTranslations()->create();
    $inactiveForm = Form::factory()->for($site, 'site')->create([
        'is_active' => false,
    ]);
    $otherSiteForm = Form::factory()->for($otherSite, 'site')->create();

    expect(ResolveFormComponentFormAction::run(null, 'not-valid', $site))->toBeNull()
        ->and(ResolveFormComponentFormAction::run(null, ResolveFormComponentFormAction::referenceFor($inactiveForm), $site))->toBeNull()
        ->and(ResolveFormComponentFormAction::run(null, ResolveFormComponentFormAction::referenceFor($otherSiteForm), $site))->toBeNull();
});

/**
 * @return Collection<int, FormFieldData>
 */
function formBuilderBoundaryFields(Form $form): Collection
{
    $schema = $form->schema;

    if ($schema instanceof DataCollection) {
        return new Collection($schema->toCollection()->all());
    }

    return collect();
}

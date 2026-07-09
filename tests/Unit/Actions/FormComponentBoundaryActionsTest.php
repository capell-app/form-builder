<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormComponentValidationRulesAction;
use Capell\FormBuilder\Actions\GuardFormSubmissionRateLimitAction;
use Capell\FormBuilder\Actions\ResolveFormComponentStepStateAction;
use Capell\FormBuilder\Data\FormComponentStepStateData;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormStepsAction;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;

it('groups visible form fields into ordered form steps', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'step_key' => 'contact',
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'step_key' => 'contact',
            ],
            [
                'key' => 'budget',
                'label' => 'Budget',
                'type' => 'number',
                'step_key' => 'project details',
            ],
            [
                'key' => 'hidden_budget_note',
                'label' => 'Budget note',
                'type' => 'textarea',
                'step_key' => 'project details',
                'visibility_conditions' => [
                    [
                        'field_key' => 'budget',
                        'operator' => 'greater_than',
                        'value' => 1000,
                    ],
                ],
            ],
        ],
    ]);

    $steps = BuildFormStepsAction::run($form, ['budget' => 500]);
    $contactStep = formBuilderStepAt($steps, 0);
    $projectStep = formBuilderStepAt($steps, 1);

    expect($steps)->toHaveCount(2)
        ->and($contactStep->key)->toBe('contact')
        ->and($contactStep->label)->toBe('Contact')
        ->and($contactStep->fields->pluck('key')->all())->toBe(['name', 'email'])
        ->and($projectStep->key)->toBe('project-details')
        ->and($projectStep->label)->toBe('Project Details')
        ->and($projectStep->fields->pluck('key')->all())->toBe(['budget']);
});

it('places fields without a step key in the default step', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
            ],
        ],
    ]);

    $steps = BuildFormStepsAction::run($form);
    $step = formBuilderStepAt($steps, 0);

    expect($steps)->toHaveCount(1)
        ->and($step->key)->toBe('default')
        ->and($step->label)->toBe(__('capell-form-builder::form.default_step'))
        ->and($step->fields->pluck('key')->all())->toBe(['message']);
});

/**
 * @param  Collection<int, FormStepData>  $steps
 */
function formBuilderStepAt(Collection $steps, int $index): FormStepData
{
    $step = $steps->get($index);

    throw_unless($step instanceof FormStepData, RuntimeException::class, 'Expected form step was not built.');

    return $step;
}

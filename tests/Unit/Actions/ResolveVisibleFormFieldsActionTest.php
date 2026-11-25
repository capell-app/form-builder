<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction;
use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Models\Form;

function chainedConditionForm(): Form
{
    return Form::factory()->make([
        'schema' => [
            ['key' => 'plan', 'label' => 'Plan', 'type' => 'text'],
            [
                'key' => 'company',
                'label' => 'Company',
                'type' => 'text',
                'visibility_conditions' => [['field_key' => 'plan', 'operator' => 'equals', 'value' => 'team']],
            ],
            [
                'key' => 'seats',
                'label' => 'Seats',
                'type' => 'text',
                'visibility_conditions' => [['field_key' => 'company', 'operator' => 'filled']],
            ],
        ],
    ]);
}

it('hides a field whose controlling field is itself hidden, even when the browser still holds a stale answer for it', function (): void {
    $keys = static fn (array $input): array => ResolveVisibleFormFieldsAction::run(chainedConditionForm(), $input)
        ->map(static fn ($field): string => $field->key)
        ->all();

    expect($keys(['plan' => 'team', 'company' => 'Acme', 'seats' => '5']))->toBe(['plan', 'company', 'seats'])
        // The visitor changed the plan back: company is hidden, so its old answer cannot keep seats visible.
        ->and($keys(['plan' => 'solo', 'company' => 'Acme', 'seats' => '5']))->toBe(['plan']);
});

it('gives validation and storage the same visible fields for a chained condition', function (): void {
    $form = chainedConditionForm();
    $input = ['plan' => 'solo', 'company' => 'Acme', 'seats' => '5'];

    $rules = BuildFormValidationRulesAction::run($form, $input);
    $validated = array_intersect_key($input, $rules);
    $stored = BuildSubmissionPayloadDataAction::run($form, $validated)->values;

    expect(array_keys($rules))->toBe(['plan'])
        ->and($stored)->toBe(['plan' => 'solo']);
});

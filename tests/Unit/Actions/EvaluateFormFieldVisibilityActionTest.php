<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldConditionOperator;

it('treats fields without visibility conditions as visible', function (): void {
    $field = FormFieldData::from([
        'key' => 'message',
        'label' => 'Message',
        'type' => 'textarea',
    ]);

    expect(EvaluateFormFieldVisibilityAction::run($field, []))->toBeTrue();
});

it('requires every visibility condition to match', function (): void {
    $field = FormFieldData::from([
        'key' => 'support_message',
        'label' => 'Support message',
        'type' => 'textarea',
        'visibility_conditions' => [
            ['field_key' => 'interest', 'operator' => 'equals', 'value' => 'support'],
            ['field_key' => 'email', 'operator' => 'filled'],
        ],
    ]);

    expect(EvaluateFormFieldVisibilityAction::run($field, [
        'interest' => 'support',
        'email' => 'ben@example.com',
    ]))->toBeTrue()
        ->and(EvaluateFormFieldVisibilityAction::run($field, [
            'interest' => 'sales',
            'email' => 'ben@example.com',
        ]))->toBeFalse();
});

it('evaluates supported condition operators', function (FormFieldConditionOperator $operator, mixed $actualValue, mixed $expectedValue, bool $result): void {
    $field = FormFieldData::from([
        'key' => 'conditional',
        'label' => 'Conditional',
        'type' => 'text',
        'visibility_conditions' => [
            [
                'field_key' => 'source',
                'operator' => $operator->value,
                'value' => $expectedValue,
            ],
        ],
    ]);

    expect(EvaluateFormFieldVisibilityAction::run($field, ['source' => $actualValue]))->toBe($result);
})->with([
    'equals numeric string' => [FormFieldConditionOperator::Equals, '10', 10, true],
    'not equals' => [FormFieldConditionOperator::NotEquals, 'sales', 'support', true],
    'filled' => [FormFieldConditionOperator::Filled, 'Ben', null, true],
    'blank' => [FormFieldConditionOperator::Blank, '', null, true],
    'contains array value' => [FormFieldConditionOperator::Contains, ['sales', 'support'], 'support', true],
    'contains string value' => [FormFieldConditionOperator::Contains, 'migration support', 'support', true],
    'greater than' => [FormFieldConditionOperator::GreaterThan, '15', 10, true],
    'less than' => [FormFieldConditionOperator::LessThan, 5, '10', true],
]);

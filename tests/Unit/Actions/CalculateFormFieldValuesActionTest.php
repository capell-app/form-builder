<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Models\Form;

it('calculates visible calculated field values from numeric inputs', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'quantity',
                'label' => 'Quantity',
                'type' => 'number',
            ],
            [
                'key' => 'unit_price',
                'label' => 'Unit price',
                'type' => 'number',
            ],
            [
                'key' => 'total',
                'label' => 'Total',
                'type' => 'calculation',
                'calculation_expression' => '(quantity * unit_price) + 10',
            ],
        ],
    ]);

    expect(CalculateFormFieldValuesAction::run($form, [
        'quantity' => '3',
        'unit_price' => '15',
    ]))->toBe([
        'quantity' => '3',
        'unit_price' => '15',
        'total' => 55,
    ]);
});

it('only calculates visible calculated fields', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'plan',
                'label' => 'Plan',
                'type' => 'select',
                'options' => [
                    'free' => 'Free',
                    'paid' => 'Paid',
                ],
            ],
            [
                'key' => 'total',
                'label' => 'Total',
                'type' => 'calculation',
                'calculation_expression' => 'seats * 100',
                'visibility_conditions' => [
                    [
                        'field_key' => 'plan',
                        'operator' => 'equals',
                        'value' => 'paid',
                    ],
                ],
            ],
        ],
    ]);

    expect(CalculateFormFieldValuesAction::run($form, [
        'plan' => 'free',
        'seats' => 2,
    ]))->toBe([
        'plan' => 'free',
        'seats' => 2,
    ])->and(CalculateFormFieldValuesAction::run($form, [
        'plan' => 'paid',
        'seats' => 2,
    ]))->toBe([
        'plan' => 'paid',
        'seats' => 2,
        'total' => 200,
    ]);
});

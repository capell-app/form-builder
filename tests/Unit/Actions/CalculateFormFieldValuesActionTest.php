<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Models\Form;
use Illuminate\Validation\ValidationException;

it('evaluates unary operators and decimal literals with arithmetic precedence', function (string $expression, int|float $expected): void {
    $form = Form::factory()->make([
        'schema' => [[
            'key' => 'total',
            'label' => 'Total',
            'type' => 'calculation',
            'calculation_expression' => $expression,
        ]],
    ]);

    expect(CalculateFormFieldValuesAction::run($form, ['quantity' => -3])['total'])->toBe($expected);
})->with([
    'negative factor' => ['2 * -3', -6],
    'subtract negative' => ['10 - -2', 12],
    'leading decimal point' => ['.5 * 8', 4],
    'unary group' => ['-(2 + 3) * 4', -20],
    'unary field' => ['2 * -quantity', 6],
    'unary plus' => ['2 * +3', 6],
    'consecutive unary signs' => ['--2 + -+.5', 1.5],
    'left associative division' => ['8 / 4 / 2', 1],
    'decimal division' => ['1.5 / .5', 3],
]);

it('rejects malformed or unsupported calculations instead of discarding tokens or inventing operands', function (string $expression): void {
    $form = Form::factory()->make([
        'schema' => [[
            'key' => 'total',
            'label' => 'Total',
            'type' => 'calculation',
            'calculation_expression' => $expression,
        ]],
    ]);

    expect(fn (): array => CalculateFormFieldValuesAction::run($form))
        ->toThrow(ValidationException::class);
})->with([
    'missing operand' => '2 *',
    'missing operator' => '2 3',
    'extra decimal point' => '1.2.3',
    'unclosed group' => '(2 + 3',
    'unopened group' => '2 + 3)',
    'empty group' => '()',
    'implicit multiplication' => '2(3)',
    'unsupported power' => '2 ^ 3',
    'unsupported modulo' => '5 % 2',
    'function call' => 'abs(-3)',
    'punctuation' => '2; + 3',
    'exponent notation' => '1e3',
    'too long' => str_repeat('1+', 2048) . '1',
]);

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

<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\ValidateFormFieldKeysAction;
use Illuminate\Validation\ValidationException;

it('reports every conflicting raw repeater row and permits an explicit correction', function (): void {
    $schema = [
        'first-row' => ['key' => 'answer', 'label' => 'First answer', 'step_key' => 'one'],
        'second-row' => ['key' => 'answer', 'label' => 'Second answer', 'step_key' => 'two'],
        'third-row' => ['key' => 'answer', 'label' => 'Third answer', 'visibility_conditions' => [['field_key' => 'choice']]],
        'unique-row' => ['key' => 'unique', 'label' => 'Unique answer'],
    ];
    $before = $schema;

    try {
        ValidateFormFieldKeysAction::run($schema);
        test()->fail('Expected duplicate field keys to fail validation.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe([
            'data.schema.first-row.key', 'data.schema.second-row.key', 'data.schema.third-row.key',
        ]);
        foreach ($exception->errors() as $messages) {
            expect($messages)->toBe([__('capell-form-builder::form.admin.duplicate_field_key', [
                'fields' => 'First answer, Second answer, Third answer',
            ])]);
        }
    }

    expect($schema)->toBe($before);
    $schema['second-row']['key'] = 'second_answer';
    $schema['third-row']['key'] = 'third_answer';
    ValidateFormFieldKeysAction::run($schema);
});

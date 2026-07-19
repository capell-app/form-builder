<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\ResolveFormInitialValuesAction;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;

it('keeps bounded scalar values only for declared hidden fields', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'flag', 'label' => 'Flag', 'type' => FormFieldType::Hidden->value],
            ['key' => 'count', 'label' => 'Count', 'type' => FormFieldType::Hidden->value],
            ['key' => 'ratio', 'label' => 'Ratio', 'type' => FormFieldType::Hidden->value],
            ['key' => 'context', 'label' => 'Context', 'type' => FormFieldType::Hidden->value],
            ['key' => 'oversized', 'label' => 'Oversized', 'type' => FormFieldType::Hidden->value],
            ['key' => 'unsafe', 'label' => 'Unsafe', 'type' => FormFieldType::Hidden->value],
            ['key' => 'name', 'label' => 'Name', 'type' => FormFieldType::Text->value],
        ],
    ]);

    expect(ResolveFormInitialValuesAction::run($form, [
        'flag' => false,
        'count' => 0,
        'ratio' => 1.5,
        'context' => '',
        'oversized' => str_repeat('x', 2001),
        'unsafe' => ['nested' => 'value'],
        'name' => 'Must not prefill',
        'unknown' => 'Must not leak',
    ]))->toBe([
        'flag' => false,
        'count' => 0,
        'ratio' => 1.5,
        'context' => '',
    ]);
});

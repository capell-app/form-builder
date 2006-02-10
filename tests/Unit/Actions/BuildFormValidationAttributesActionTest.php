<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormValidationAttributesAction;
use Capell\FormBuilder\Models\Form;

it('maps editor labels for visible fields with the requested validation prefix', function (string $prefix): void {
    $form = Form::factory()->make([
        'schema' => [
            ['key' => 'company_name', 'label' => 'Company name', 'type' => 'text'],
            [
                'key' => 'contact_name', 'label' => 'Contact <name>', 'type' => 'text',
                'visibility_conditions' => [
                    ['field_key' => 'company_name', 'operator' => 'equals', 'value' => 'Capell'],
                ],
            ],
        ],
    ]);

    expect(BuildFormValidationAttributesAction::run($form, [], $prefix))
        ->toBe([$prefix . 'company_name' => 'Company name']);
    expect(BuildFormValidationAttributesAction::run($form, ['company_name' => 'Capell'], $prefix))
        ->toBe([$prefix . 'company_name' => 'Company name', $prefix . 'contact_name' => 'Contact <name>']);
})->with(['direct' => '', 'livewire' => 'data.']);

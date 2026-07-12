<?php

declare(strict_types=1);

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldConditionOperator;
use Capell\FormBuilder\Enums\FormFieldType;

it('creates form field data from editor state', function (): void {
    $field = FormFieldData::from([
        'key' => 'email',
        'label' => 'Email address',
        'type' => 'email',
        'required' => true,
        'placeholder' => 'you@example.com',
        'help_text' => 'Used to reply to your enquiry.',
        'options' => [],
        'default_value' => null,
        'validation_rules' => ['email'],
        'step_key' => 'contact',
        'calculation_expression' => null,
        'accepted_file_types' => [],
        'max_file_size_kilobytes' => null,
        'payment_amount_cents' => null,
        'payment_currency' => null,
        'visibility_conditions' => [
            [
                'field_key' => 'interest',
                'operator' => 'equals',
                'value' => 'support',
            ],
        ],
    ]);

    expect($field->key)->toBe('email')
        ->and($field->type)->toBe(FormFieldType::Email)
        ->and($field->required)->toBeTrue()
        ->and($field->stepKey)->toBe('contact')
        ->and($field->validationRules)->toBe(['email'])
        ->and($field->visibilityConditions()[0]->fieldKey)->toBe('interest')
        ->and($field->visibilityConditions()[0]->operator)->toBe(FormFieldConditionOperator::Equals)
        ->and($field->visibilityConditions()[0]->value)->toBe('support');
});

it('provides simple default form settings', function (): void {
    $settings = FormSettingsData::from([]);

    expect($settings->successMessage)->toBeNull()
        ->and($settings->storeSubmissions)->toBeTrue()
        ->and($settings->notificationEmail)->toBeNull()
        ->and($settings->autoresponderSubject)->toBeNull()
        ->and($settings->autoresponderBody)->toBeNull()
        ->and($settings->successRedirectUrl)->toBeNull()
        ->and($settings->collectIpAddress)->toBeTrue()
        ->and($settings->collectUserAgent)->toBeTrue();
});

it('wraps submitted values in payload data', function (): void {
    $payload = SubmissionPayloadData::from([
        'values' => [
            'name' => 'Ben',
            'email' => 'ben@example.com',
        ],
    ]);

    expect($payload->values)->toBe([
        'name' => 'Ben',
        'email' => 'ben@example.com',
    ]);
});

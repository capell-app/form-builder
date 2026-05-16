<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Models\Form;

it('builds validation rules from field data', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'required' => true,
                'validation_rules' => ['max:120'],
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'validation_rules' => ['email'],
            ],
            [
                'key' => 'company_website',
                'label' => 'Company website',
                'type' => 'honeypot',
                'required' => false,
            ],
        ],
    ]);

    expect(BuildFormValidationRulesAction::run($form))->toBe([
        'name' => ['required', 'string', 'max:255', 'max:120'],
        'email' => ['required', 'email', 'max:255'],
        'company_website' => ['nullable', 'prohibited'],
    ]);
});

it('ignores unsupported editor validation rules', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'required' => false,
                'validation_rules' => ['max:500', 'starts_with:<?php'],
            ],
        ],
    ]);

    expect(BuildFormValidationRulesAction::run($form))->toBe([
        'message' => ['nullable', 'string', 'max:10000', 'max:500'],
    ]);
});

it('adds conservative default maximum lengths and clamps editor maximums', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'required' => false,
            ],
            [
                'key' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'required' => false,
                'validation_rules' => ['max:50000'],
            ],
            [
                'key' => 'redirect',
                'label' => 'Redirect',
                'type' => 'hidden',
                'required' => false,
                'validation_rules' => ['max:1000'],
            ],
        ],
    ]);

    expect(BuildFormValidationRulesAction::run($form))->toBe([
        'name' => ['nullable', 'string', 'max:255'],
        'message' => ['nullable', 'string', 'max:10000'],
        'redirect' => ['nullable', 'string', 'max:255'],
    ]);
});

it('allows optional checkboxes to be false', function (): void {
    $form = Form::factory()->make([
        'schema' => [
            [
                'key' => 'newsletter',
                'label' => 'Newsletter',
                'type' => 'checkbox',
                'required' => false,
            ],
            [
                'key' => 'terms',
                'label' => 'Terms',
                'type' => 'checkbox',
                'required' => true,
            ],
        ],
    ]);

    expect(BuildFormValidationRulesAction::run($form))->toBe([
        'newsletter' => ['nullable', 'boolean'],
        'terms' => ['required', 'accepted'],
    ]);
});

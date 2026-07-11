<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\InstallThemeDemoFormsAction;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;

it('idempotently installs theme demo form blueprints for a site', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $payload = json_encode([[
        'handle' => 'studio-enquiry',
        'name' => 'Studio enquiry',
        'description' => 'Share the brief.',
        'success_message' => 'Thanks — we will reply shortly.',
        'fields' => [
            [
                'name' => 'email_address',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'validation_rules' => ['email'],
            ],
        ],
    ]], JSON_THROW_ON_ERROR);

    InstallThemeDemoFormsAction::run($site->getKey(), $payload);
    InstallThemeDemoFormsAction::run($site->getKey(), $payload);

    $form = Form::query()->whereBelongsTo($site)->where('handle', 'studio-enquiry')->firstOrFail();
    $field = $form->schema->first();

    expect(Form::query()->whereBelongsTo($site)->count())->toBe(1)
        ->and($form->name)->toBe('Studio enquiry')
        ->and($form->description)->toBe('Share the brief.')
        ->and($form->settings?->successMessage)->toBe('Thanks — we will reply shortly.')
        ->and($field?->key)->toBe('email_address')
        ->and($field?->type)->toBe(FormFieldType::Email)
        ->and($field?->required)->toBeTrue();
});

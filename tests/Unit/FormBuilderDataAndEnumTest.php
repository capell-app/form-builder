<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildSubmissionPayloadEntriesAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Enums\ResourceEnum;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Health\FormBuilderHealthCheck;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Models\Submission;

it('declares enum labels components resources and health compatibility', function (): void {
    expect(FormFieldType::Honeypot->isSpamTrap())->toBeTrue()
        ->and(FormFieldType::Honeypot->isStoredInPayload())->toBeFalse()
        ->and(FormFieldType::Email->getLabel())->toBeString()->not->toBe('')
        ->and(SubmissionStatus::New->getColor())->toBe('info')
        ->and(SubmissionStatus::Spam->getColor())->toBe('danger')
        ->and(SubmissionStatus::Read->getLabel())->toBeString()->not->toBe('')
        ->and(LivewireComponentEnum::getComponents())->toBe([
            'capell-form-builder::form' => FormComponent::class,
            'capell-form-builder::element.form' => FormElementComponent::class,
            'public-form-fields' => FormComponent::class,
            'public-form' => FormElementComponent::class,
        ])
        ->and(ResourceEnum::Submissions->value)->toBe(SubmissionResource::class)
        ->and(FormBuilderHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('maps submission metadata from snake case arrays', function (): void {
    $meta = SubmissionMetaData::from([
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Capell Browser',
        'url' => 'https://example.test/contact',
        'referer' => 'https://example.test/',
    ]);

    expect($meta->ipAddress)->toBe('203.0.113.10')
        ->and($meta->userAgent)->toBe('Capell Browser')
        ->and($meta->url)->toBe('https://example.test/contact')
        ->and($meta->referer)->toBe('https://example.test/');
});

it('formats payload entries without a loaded form schema', function (): void {
    $submission = new Submission;
    $submission->payload = SubmissionPayloadData::from([
        'values' => [
            'full_name' => 'Ben Johnson',
            'selected_options' => ['One', false, null],
            'empty_value' => '',
        ],
    ]);

    expect(BuildSubmissionPayloadEntriesAction::run($submission)->all())->toBe([
        [
            'key' => 'full_name',
            'label' => 'Full Name',
            'value' => 'Ben Johnson',
        ],
        [
            'key' => 'selected_options',
            'label' => 'Selected Options',
            'value' => 'One, ' . __('capell-form-builder::generic.boolean.no') . ', ' . __('capell-form-builder::generic.empty_value'),
        ],
        [
            'key' => 'empty_value',
            'label' => 'Empty Value',
            'value' => __('capell-form-builder::generic.empty_value'),
        ],
    ]);
});

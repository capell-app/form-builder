<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;
use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;
use Capell\FormBuilder\Actions\BuildFormStepsAction;
use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction;
use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction;
use Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction;
use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Manifest\FormElementComponentContribution;
use Capell\FormBuilder\Manifest\FormModelContribution;
use Capell\FormBuilder\Manifest\FormResourceContribution;
use Capell\FormBuilder\Manifest\SubmissionModelContribution;
use Capell\FormBuilder\Manifest\SubmissionResourceContribution;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\File;

describe('form-builder capell.json manifest', function (): void {
    it('declares requires using full composer package names', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
        );

        $requires = $manifest['dependencies']['requires'] ?? [];

        foreach ($requires as $requirement) {
            expect($requirement)->toContain('/');
        }
    });

    it('requires the Capell packages it imports directly', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
        );

        expect($manifest['dependencies']['requires'])->toContain('capell-app/core')
            ->and($manifest['dependencies']['requires'])->toContain('capell-app/admin')
            ->and($manifest['dependencies']['requires'])->toContain('capell-app/frontend');
    });

    it('declares implemented advanced form features and optional payment support', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        expect($manifest['dependencies']['supports'])->toContain('capell-app/payments')
            ->and($manifest['capabilities'])->toContain(
                'form-builder-conditional-logic',
                'form-builder-multi-step',
                'form-builder-calculations',
                'form-builder-file-upload-rules',
                'form-builder-spam-scoring',
                'form-builder-payment-fields',
                'form-builder-submission-workflows',
            )
            ->and($manifest['actions'])->toHaveKey('buildFormSteps', BuildFormStepsAction::class)
            ->and($manifest['actions'])->toHaveKey('buildFormValidationRules', BuildFormValidationRulesAction::class)
            ->and($manifest['actions'])->toHaveKey('buildSubmissionPayloadData', BuildSubmissionPayloadDataAction::class)
            ->and($manifest['actions'])->toHaveKey('calculateFormFieldValues', CalculateFormFieldValuesAction::class)
            ->and($manifest['actions'])->toHaveKey('calculateSubmissionSpamScore', CalculateSubmissionSpamScoreAction::class)
            ->and($manifest['actions'])->toHaveKey('createFormPaymentCheckoutRedirectUrl', CreateFormPaymentCheckoutRedirectUrlAction::class)
            ->and($manifest['actions'])->toHaveKey('createSubmission', CreateSubmissionAction::class)
            ->and($manifest['actions'])->toHaveKey('dispatchUnstoredFormSubmission', DispatchUnstoredFormSubmissionAction::class)
            ->and($manifest['actions'])->toHaveKey('evaluateFormFieldVisibility', EvaluateFormFieldVisibilityAction::class)
            ->and($manifest['actions'])->toHaveKey('resolveVisibleFormFields', ResolveVisibleFormFieldsAction::class);
    });

    it('declares implemented admin resource frontend component and models', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        expect($manifest['contributes'])->toContain([
            'type' => 'admin-resource',
            'class' => FormResourceContribution::class,
            'resourceClass' => FormResource::class,
        ])
            ->and($manifest['contributes'])->toContain([
                'type' => 'admin-resource',
                'class' => SubmissionResourceContribution::class,
                'resourceClass' => SubmissionResource::class,
            ])
            ->and($manifest['contributes'])->toContain([
                'type' => 'frontend-component',
                'class' => FormElementComponentContribution::class,
                'componentClass' => FormElementComponent::class,
                'surface' => 'frontend',
            ])
            ->and($manifest['contributes'])->toContain([
                'type' => 'model',
                'class' => FormModelContribution::class,
                'modelClass' => Form::class,
            ])
            ->and($manifest['contributes'])->toContain([
                'type' => 'model',
                'class' => SubmissionModelContribution::class,
                'modelClass' => Submission::class,
            ])
            ->and(class_implements(FormResourceContribution::class))->toContain(RegistersExtensionAdminResource::class)
            ->and(class_implements(SubmissionResourceContribution::class))->toContain(RegistersExtensionAdminResource::class)
            ->and(class_implements(FormElementComponentContribution::class))->toContain(RegistersExtensionFrontendComponent::class)
            ->and($manifest['contributionTraceability']['deferredContributions'])->not->toContain('admin-resource', 'model');
    });

    it('declares the shipped marketplace screenshot set', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $screenshots = $manifest['marketplace']['screenshots'] ?? [];
        $screenshotPaths = [];

        if (is_array($screenshots)) {
            foreach ($screenshots as $screenshot) {
                if (! is_array($screenshot)) {
                    continue;
                }

                $path = $screenshot['path'] ?? null;

                if (is_string($path)) {
                    $screenshotPaths[] = $path;
                }
            }
        }

        expect($screenshotPaths)->toBe(['docs/assets/marketplace/extension-card.jpg']);

        foreach ($screenshotPaths as $path) {
            expect(File::exists(__DIR__ . '/../../' . $path))->toBeTrue();
        }
    });
});

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormComponentStepStateData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static FormComponentStepStateData run(Form $form, array<string, mixed> $input = [], string $currentStepKey = '')
 */
final class ResolveFormComponentStepStateAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Form $form, array $input = [], string $currentStepKey = ''): FormComponentStepStateData
    {
        $steps = BuildFormStepsAction::run($form, $input)->values();

        if ($steps->isEmpty()) {
            return FormComponentStepStateData::empty();
        }

        $currentStep = $steps->first(static fn (FormStepData $step): bool => $step->key === $currentStepKey);

        if (! $currentStep instanceof FormStepData) {
            $firstStep = $steps->first();
            $currentStepKey = $firstStep instanceof FormStepData ? $firstStep->key : '';
            $currentStep = $firstStep instanceof FormStepData ? $firstStep : null;
        }

        $currentStepIndex = $steps
            ->values()
            ->search(static fn (FormStepData $step): bool => $step->key === $currentStepKey);

        return new FormComponentStepStateData(
            steps: $steps,
            currentStepKey: $currentStepKey,
            currentStep: $currentStep instanceof FormStepData ? $currentStep : null,
            currentStepIndex: is_int($currentStepIndex) ? $currentStepIndex : 0,
        );
    }
}

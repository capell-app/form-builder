<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Illuminate\Support\Collection;

final class FormComponentStepStateData
{
    /**
     * @param  Collection<int, FormStepData>  $steps
     */
    public function __construct(
        public readonly Collection $steps,
        public readonly string $currentStepKey,
        public readonly ?FormStepData $currentStep,
        public readonly int $currentStepIndex,
    ) {}

    public static function empty(): self
    {
        return new self(
            steps: collect(),
            currentStepKey: '',
            currentStep: null,
            currentStepIndex: 0,
        );
    }

    public function stepAfter(string $stepKey): ?FormStepData
    {
        return $this->steps
            ->values()
            ->get($this->stepIndex($stepKey) + 1);
    }

    public function stepBefore(string $stepKey): ?FormStepData
    {
        return $this->steps
            ->values()
            ->get($this->stepIndex($stepKey) - 1);
    }

    public function isFinalStep(): bool
    {
        if ($this->steps->count() <= 1) {
            return true;
        }

        return $this->currentStepIndex >= $this->steps->count() - 1;
    }

    private function stepIndex(string $stepKey): int
    {
        $index = $this->steps
            ->values()
            ->search(static fn (FormStepData $step): bool => $step->key === $stepKey);

        return is_int($index) ? $index : 0;
    }
}

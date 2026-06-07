<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Livewire;

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\BuildFormStepsAction;
use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\LaravelData\DataCollection;
use Throwable;

final class FormComponent extends Component
{
    use WithFileUploads;

    private const string PackageName = 'capell-app/form-builder';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $formReference = '';

    public string $instanceId = '';

    public bool $submitted = false;

    public string $currentStepKey = '';

    private ?Form $resolvedForm = null;

    public function mount(int|string|null $handle = null, ?string $instanceId = null, ?string $formReference = null): void
    {
        $this->instanceId = $this->resolveInstanceId($instanceId);
        $this->formReference = is_string($formReference) ? $formReference : '';
        $this->resolvedForm = $this->resolveForm($handle);

        if ($this->resolvedForm instanceof Form) {
            $this->formReference = $this->encryptFormReference($this->resolvedForm);
        }

        foreach ($this->allFields() as $field) {
            $this->data[$field->key] = $field->defaultValue;
        }

        $this->currentStepKey = $this->firstStepKey();
    }

    public function nextStep(): void
    {
        $currentStep = $this->currentStep();

        if (! $currentStep instanceof FormStepData) {
            return;
        }

        $this->validate($this->rulesForFields($currentStep->fields, $this->data));

        $nextStep = $this->stepAfter($currentStep->key);

        if ($nextStep instanceof FormStepData) {
            $this->currentStepKey = $nextStep->key;
        }
    }

    public function previousStep(): void
    {
        $previousStep = $this->stepBefore($this->currentStepKey);

        if ($previousStep instanceof FormStepData) {
            $this->currentStepKey = $previousStep->key;
        }
    }

    public function submit(): void
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return;
        }

        if (! $this->isFinalStep()) {
            $this->nextStep();

            return;
        }

        $metadata = $this->metadata();
        $settings = $this->settings();
        $this->assertSubmissionIsNotRateLimited($form);

        if ($this->hasTriggeredHoneypot()) {
            if ($settings->storeSubmissions) {
                CreateSubmissionAction::run(
                    form: $form,
                    input: $this->data,
                    meta: $metadata,
                );
            }

            $this->submitted = true;
            $this->reset('data');

            return;
        }

        $this->data = CalculateFormFieldValuesAction::run($form, $this->data);

        $this->validate($this->rules($this->data));

        if ($settings->storeSubmissions) {
            CreateSubmissionAction::run(
                form: $form,
                input: $this->data,
                meta: $metadata,
            );
        } else {
            event(new FormSubmitted($form, metadata: $metadata, payload: $this->storedPayload()));
        }

        $this->submitted = true;
        $this->reset('data');
    }

    public function render(): View
    {
        $form = $this->form();

        if ($form instanceof Form) {
            $this->recordNonCacheableRenderContribution();
        }

        return view('capell-form-builder::livewire.form', [
            'fields' => $this->fields(),
            'form' => $form,
            'formInstanceId' => $this->instanceId,
            'currentStep' => $this->currentStep(),
            'currentStepIndex' => $this->currentStepIndex(),
            'settings' => $this->settings(),
            'steps' => $this->steps(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function assertSubmissionIsNotRateLimited(Form $form): void
    {
        $key = $this->rateLimitKey($form);
        $maxAttempts = $this->configuredThrottleValue('max_attempts', 12);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'data' => __('capell-form-builder::message.too_many_submissions'),
            ]);
        }

        RateLimiter::hit($key, $this->configuredThrottleValue('decay_seconds', 60));
    }

    private function rateLimitKey(Form $form): string
    {
        $email = $this->rateLimitEmailDimension();

        return 'capell-form-builder:submit:' . hash('sha256', implode('|', [
            (string) $form->getKey(),
            (string) $form->site_id,
            $email,
            (string) request()->ip(),
        ]));
    }

    private function rateLimitEmailDimension(): string
    {
        $emailField = $this->allFields()
            ->first(static fn (FormFieldData $field): bool => $field->type === FormFieldType::Email);

        if (! $emailField instanceof FormFieldData) {
            return '';
        }

        $email = $this->data[$emailField->key] ?? null;

        return is_scalar($email) ? Str::lower((string) $email) : '';
    }

    private function configuredThrottleValue(string $key, int $default): int
    {
        $value = config('capell-form-builder.throttle.' . $key);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }

    private function resolveForm(int|string|null $handle = null): ?Form
    {
        return $this->resolveFormFromReference() ?? $this->resolveFormForCurrentSite($handle);
    }

    private function resolveFormForCurrentSite(int|string|null $handle): ?Form
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        $site = $this->currentSite();
        if (! $site instanceof Site) {
            return null;
        }

        return Form::query()
            ->active()
            ->where('site_id', $site->getKey())
            ->where(function (Builder $builder) use ($handle): void {
                if (is_numeric($handle)) {
                    $builder->whereKey((int) $handle);
                }

                $builder->orWhere('handle', (string) $handle);
            })
            ->first();
    }

    private function resolveFormFromReference(): ?Form
    {
        if ($this->formReference === '') {
            return null;
        }

        try {
            $reference = json_decode(Crypt::decryptString($this->formReference), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $formId = $reference['form_id'] ?? null;
        $siteId = $reference['site_id'] ?? null;

        if (! is_numeric($formId) || ! is_numeric($siteId)) {
            return null;
        }

        $currentSite = $this->currentSite();
        if ($currentSite instanceof Site && (int) $currentSite->getKey() !== (int) $siteId) {
            return null;
        }

        return Form::query()
            ->active()
            ->whereKey((int) $formId)
            ->where('site_id', (int) $siteId)
            ->first();
    }

    private function encryptFormReference(Form $form): string
    {
        return Crypt::encryptString(json_encode([
            'form_id' => $form->getKey(),
            'site_id' => $form->site_id,
        ], JSON_THROW_ON_ERROR));
    }

    private function form(): ?Form
    {
        return $this->resolvedForm ??= $this->resolveForm();
    }

    private function resolveInstanceId(?string $instanceId): string
    {
        if ($instanceId !== null && trim($instanceId) !== '') {
            $normalizedInstanceId = Str::slug($instanceId);

            if ($normalizedInstanceId !== '') {
                return $normalizedInstanceId;
            }
        }

        return (string) Str::uuid();
    }

    /**
     * @return Collection<int, FormFieldData>
     */
    private function allFields(): Collection
    {
        $fields = $this->form()?->schema;

        if ($fields instanceof DataCollection) {
            return new Collection($fields->toCollection()->all());
        }

        return collect();
    }

    /**
     * @return Collection<int, FormFieldData>
     */
    private function fields(): Collection
    {
        $currentStep = $this->currentStep();

        if ($currentStep instanceof FormStepData) {
            return $currentStep->fields;
        }

        return $this->visibleFields();
    }

    /**
     * @return Collection<int, FormFieldData>
     */
    private function visibleFields(): Collection
    {
        $form = $this->form();

        return $form instanceof Form
            ? ResolveVisibleFormFieldsAction::run($form, $this->data)
            : collect();
    }

    /**
     * @return Collection<int, FormStepData>
     */
    private function steps(): Collection
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return collect();
        }

        $steps = BuildFormStepsAction::run($form, $this->data);

        if ($steps->isNotEmpty() && ! $steps->contains(fn (FormStepData $step): bool => $step->key === $this->currentStepKey)) {
            $firstStep = $steps->first();

            if ($firstStep instanceof FormStepData) {
                $this->currentStepKey = $firstStep->key;
            }
        }

        return $steps;
    }

    private function currentStep(): ?FormStepData
    {
        $steps = $this->steps();
        $step = $steps->first(fn (FormStepData $step): bool => $step->key === $this->currentStepKey);

        return $step instanceof FormStepData
            ? $step
            : null;
    }

    private function firstStepKey(): string
    {
        $step = $this->steps()->first();

        return $step instanceof FormStepData ? $step->key : '';
    }

    private function currentStepIndex(): int
    {
        $index = $this->steps()
            ->values()
            ->search(fn (FormStepData $step): bool => $step->key === $this->currentStepKey);

        return is_int($index) ? $index : 0;
    }

    private function stepAfter(string $stepKey): ?FormStepData
    {
        return $this->steps()
            ->values()
            ->get($this->stepIndex($stepKey) + 1);
    }

    private function stepBefore(string $stepKey): ?FormStepData
    {
        return $this->steps()
            ->values()
            ->get($this->stepIndex($stepKey) - 1);
    }

    private function stepIndex(string $stepKey): int
    {
        $index = $this->steps()
            ->values()
            ->search(fn (FormStepData $step): bool => $step->key === $stepKey);

        return is_int($index) ? $index : 0;
    }

    private function isFinalStep(): bool
    {
        $steps = $this->steps();

        return $steps->count() <= 1 || $this->currentStepIndex() >= $steps->count() - 1;
    }

    private function settings(): FormSettingsData
    {
        $form = $this->form();

        return $form?->settings instanceof FormSettingsData
            ? $form->settings
            : new FormSettingsData(successMessage: __('capell-form-builder::message.form_submitted'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string>>
     */
    private function rules(array $input = []): array
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return [];
        }

        return collect(BuildFormValidationRulesAction::run($form, $input))
            ->mapWithKeys(fn (array $rules, string $fieldKey): array => ['data.' . $fieldKey => $rules])
            ->all();
    }

    /**
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string>>
     */
    private function rulesForFields(Collection $fields, array $input = []): array
    {
        $fieldKeys = $fields->pluck('key')->all();

        return collect($this->rules($input))
            ->filter(fn (array $rules, string $fieldKey): bool => in_array(Str::after($fieldKey, 'data.'), $fieldKeys, true))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function storedPayload(): array
    {
        $payload = [];

        foreach ($this->visibleFields() as $field) {
            if (! $field->type->isStoredInPayload()) {
                continue;
            }

            if (array_key_exists($field->key, $this->data)) {
                $payload[$field->key] = $this->data[$field->key];
            }
        }

        return $payload;
    }

    private function hasTriggeredHoneypot(): bool
    {
        foreach ($this->visibleFields() as $field) {
            if (! $field->type->isSpamTrap()) {
                continue;
            }

            if (filled($this->data[$field->key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function metadata(): SubmissionMetaData
    {
        $request = request();
        $settings = $this->settings();

        return new SubmissionMetaData(
            ipAddress: $settings->collectIpAddress ? $request->ip() : null,
            userAgent: $settings->collectUserAgent ? $request->userAgent() : null,
            url: $request->fullUrl(),
            referer: $request->headers->get('referer'),
        );
    }

    private function currentSite(): ?Site
    {
        try {
            $site = Frontend::site();

            return $site instanceof Site ? $site : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function recordNonCacheableRenderContribution(): void
    {
        RecordExtensionRenderContributionAction::run(
            packageName: self::PackageName,
            surface: 'frontend',
            contributionType: 'livewire-component',
            contributionClass: self::class,
            elapsedMilliseconds: 0.0,
            frontendRenderBudgetMs: 20,
            cacheTags: ['form-builder'],
            cacheable: false,
            sensitiveOutput: false,
            variesBy: ['site', 'locale'],
        );
    }
}

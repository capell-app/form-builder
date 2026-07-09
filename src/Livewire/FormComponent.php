<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Livewire;

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\BuildFormComponentValidationRulesAction;
use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction;
use Capell\FormBuilder\Actions\GuardFormSubmissionRateLimitAction;
use Capell\FormBuilder\Actions\ResolveFormComponentStepStateAction;
use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Data\FormComponentStepStateData;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
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
            $this->data[$field->key] = $this->defaultValueForField($field);
        }

        $this->currentStepKey = $this->firstStepKey();
    }

    public function nextStep(): void
    {
        $form = $this->form();
        $currentStep = $this->currentStep();

        if (! $form instanceof Form || ! $currentStep instanceof FormStepData) {
            return;
        }

        $this->validate(BuildFormComponentValidationRulesAction::run($form, $this->data, $currentStep->fields));

        $nextStep = $this->stepState()->stepAfter($currentStep->key);

        if ($nextStep instanceof FormStepData) {
            $this->currentStepKey = $nextStep->key;
        }
    }

    public function previousStep(): void
    {
        $previousStep = $this->stepState()->stepBefore($this->currentStepKey);

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
        GuardFormSubmissionRateLimitAction::run($form, $this->allFields(), $this->data, request()->ip());

        if (! $this->hasTriggeredHoneypot()) {
            $this->data = CalculateFormFieldValuesAction::run($form, $this->data);
            $this->validate(BuildFormComponentValidationRulesAction::run($form, $this->data));
        }

        try {
            if ($settings->storeSubmissions) {
                $submission = CreateSubmissionAction::run(
                    form: $form,
                    input: $this->data,
                    meta: $metadata,
                );

                $this->submitted = true;
                $this->reset('data');

                if ($this->redirectToPaymentCheckout($submission)) {
                    return;
                }

                $this->redirectAfterSuccess($settings);

                return;
            }

            DispatchUnstoredFormSubmissionAction::run(
                form: $form,
                input: $this->data,
                meta: $metadata,
            );
        } catch (ValidationException $validationException) {
            throw $this->normalizeActionValidationException($validationException);
        }

        $this->submitted = true;
        $this->reset('data');
        $this->redirectAfterSuccess($settings);
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
            'hasPaymentField' => $this->hasPaymentField(),
            'settings' => $this->settings(),
            'steps' => $this->steps(),
        ]);
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

    private function defaultValueForField(FormFieldData $field): mixed
    {
        if ($field->type === FormFieldType::Payment && is_int($field->paymentAmountCents) && $field->paymentAmountCents > 0) {
            return $field->paymentAmountCents;
        }

        return $field->defaultValue;
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
        return $this->stepState()->steps;
    }

    private function currentStep(): ?FormStepData
    {
        return $this->stepState()->currentStep;
    }

    private function firstStepKey(): string
    {
        return $this->stepState()->currentStepKey;
    }

    private function currentStepIndex(): int
    {
        return $this->stepState()->currentStepIndex;
    }

    private function isFinalStep(): bool
    {
        return $this->stepState()->isFinalStep();
    }

    private function stepState(): FormComponentStepStateData
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return FormComponentStepStateData::empty();
        }

        $state = ResolveFormComponentStepStateAction::run($form, $this->data, $this->currentStepKey);
        $this->currentStepKey = $state->currentStepKey;

        return $state;
    }

    private function settings(): FormSettingsData
    {
        $form = $this->form();

        return $form?->settings instanceof FormSettingsData
            ? $form->settings
            : new FormSettingsData(successMessage: __('capell-form-builder::message.form_submitted'));
    }

    private function redirectAfterSuccess(FormSettingsData $settings): void
    {
        $redirectUrl = is_string($settings->successRedirectUrl) ? trim($settings->successRedirectUrl) : '';

        if ($redirectUrl !== '') {
            $this->redirect($redirectUrl);
        }
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

    private function hasPaymentField(): bool
    {
        return $this->allFields()
            ->contains(static fn (FormFieldData $field): bool => $field->type === FormFieldType::Payment);
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

    private function redirectToPaymentCheckout(Submission $submission): bool
    {
        $checkoutUrl = CreateFormPaymentCheckoutRedirectUrlAction::run($submission);

        if ($checkoutUrl === null) {
            return false;
        }

        $this->redirect($checkoutUrl);

        return true;
    }

    private function normalizeActionValidationException(ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $fieldMessages) {
            $fieldKey = (string) $key;
            $messages[str_starts_with($fieldKey, 'data.') ? $fieldKey : 'data.' . $fieldKey] = $fieldMessages;
        }

        return ValidationException::withMessages($messages);
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

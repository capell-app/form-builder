<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Livewire;

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\LaravelData\DataCollection;
use Throwable;

final class FormComponent extends Component
{
    private const string PackageName = 'capell-app/form-builder';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $formReference = '';

    public string $instanceId = '';

    public bool $submitted = false;

    private ?Form $resolvedForm = null;

    public function mount(int|string|null $handle = null, ?string $instanceId = null, ?string $formReference = null): void
    {
        $this->instanceId = $this->resolveInstanceId($instanceId);
        $this->formReference = is_string($formReference) ? $formReference : '';
        $this->resolvedForm = $this->resolveForm($handle);

        if ($this->resolvedForm instanceof Form) {
            $this->formReference = $this->encryptFormReference($this->resolvedForm);
        }

        foreach ($this->fields() as $field) {
            $this->data[$field->key] = $field->defaultValue;
        }
    }

    public function submit(): void
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return;
        }

        $metadata = $this->metadata();
        $settings = $this->settings();

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

        $this->validate($this->rules());

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
            'settings' => $this->settings(),
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

    /**
     * @return Collection<int, FormFieldData>
     */
    private function fields(): Collection
    {
        $fields = $this->form()?->schema;

        if ($fields instanceof DataCollection) {
            return $fields->toCollection();
        }

        return collect();
    }

    private function settings(): FormSettingsData
    {
        $form = $this->form();

        return $form?->settings instanceof FormSettingsData
            ? $form->settings
            : new FormSettingsData(successMessage: __('capell-form-builder::message.form_submitted'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $form = $this->form();

        if (! $form instanceof Form) {
            return [];
        }

        return collect(BuildFormValidationRulesAction::run($form))
            ->mapWithKeys(fn (array $rules, string $fieldKey): array => ['data.' . $fieldKey => $rules])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function storedPayload(): array
    {
        $payload = [];

        foreach ($this->fields() as $field) {
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
        foreach ($this->fields() as $field) {
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

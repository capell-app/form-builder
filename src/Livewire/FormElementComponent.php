<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Livewire;

use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;
use Capell\Core\Models\Site;
use Capell\Core\Support\Security\PublicUrlSanitizer;
use Capell\FormBuilder\Models\Form;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class FormElementComponent extends Component implements RegistersExtensionFrontendComponent
{
    private const string PackageName = 'capell-app/form-builder';

    public string $formReference = '';

    public string $instanceId = '';

    public string $fallbackMessage = '';

    public string $fallbackLabel = '';

    public ?string $fallbackUrl = null;

    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    /**
     * @param  array<string, mixed>  $widgetData
     */
    public function mount(array $widgetData = [], int|string|null $handle = null): void
    {
        $this->instanceId = $this->resolveInstanceId($widgetData);
        $this->fallbackMessage = $this->stringValue($widgetData, 'fallback_message');
        $this->fallbackLabel = $this->stringValue($widgetData, 'fallback_label');
        $this->fallbackUrl = PublicUrlSanitizer::sanitize($widgetData['fallback_url'] ?? null);

        $form = $this->resolveFormForCurrentSite($handle ?? $this->resolveHandle($widgetData));

        if ($form instanceof Form) {
            $this->formReference = $this->encryptFormReference($form);
        }
    }

    public function render(): View
    {
        if ($this->formReference !== '') {
            RecordExtensionRenderContributionAction::run(
                packageName: self::PackageName,
                surface: 'frontend',
                contributionType: 'frontend-component',
                contributionClass: self::class,
                elapsedMilliseconds: 0.0,
                frontendRenderBudgetMs: 20,
                cacheTags: ['form-builder'],
                cacheable: false,
                sensitiveOutput: false,
                variesBy: ['site', 'locale'],
            );
        }

        return view('capell-form-builder::livewire.form-element');
    }

    /**
     * @param  array<string, mixed>  $widgetData
     */
    private function resolveHandle(array $widgetData): int|string|null
    {
        $handle = $widgetData['form_handle'] ?? $widgetData['handle'] ?? null;

        return is_int($handle) || is_string($handle) ? $handle : null;
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

    private function encryptFormReference(Form $form): string
    {
        return Crypt::encryptString(json_encode([
            'form_id' => $form->getKey(),
            'site_id' => $form->site_id,
        ], JSON_THROW_ON_ERROR));
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

    /**
     * @param  array<string, mixed>  $widgetData
     */
    private function resolveInstanceId(array $widgetData): string
    {
        $instanceId = $widgetData['instance_id']
            ?? $widgetData['instanceId']
            ?? $widgetData['element_instance_id']
            ?? null;

        if (is_int($instanceId) || is_string($instanceId)) {
            $normalizedInstanceId = Str::slug((string) $instanceId);

            if ($normalizedInstanceId !== '') {
                return $normalizedInstanceId;
            }
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $widgetData
     */
    private function stringValue(array $widgetData, string $key): string
    {
        $value = $widgetData[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}

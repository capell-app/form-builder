<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Livewire;

use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;
use Capell\Core\Models\Site;
use Capell\Core\Support\Security\PublicUrlSanitizer;
use Capell\FormBuilder\Actions\FindActiveSiteFormAction;
use Capell\FormBuilder\Actions\ResolveFormComponentFormAction;
use Capell\FormBuilder\Actions\ResolveFormRequestSiteAction;
use Capell\FormBuilder\Actions\ResolveFormWidgetPlacementAction;
use Capell\FormBuilder\Data\FormWidgetPlacementData;
use Capell\FormBuilder\Models\Form;
use Capell\Frontend\Actions\Performance\RecordExtensionRenderContributionAction;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Override;
use Throwable;

class FormElementComponent extends Component implements RegistersExtensionFrontendComponent
{
    private const string PackageName = 'capell-app/form-builder';

    public string $formReference = '';

    public string $formHandle = '';

    #[Locked]
    public string $siteReference = '';

    public string $instanceId = '';

    public string $fallbackMessage = '';

    public string $fallbackLabel = '';

    public ?string $fallbackUrl = null;

    /**
     * A Layout Builder widget shows nothing at all when its form is missing,
     * inactive or belongs to another site, instead of an unavailable notice.
     */
    #[Locked]
    public bool $hideWhenUnavailable = false;

    #[Locked]
    public bool $loadAttempted = false;

    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /**
     * @param  array<string, mixed>  $widgetData
     */
    public function mount(
        array $widgetData = [],
        int|string|null $handle = null,
        string $widgetReference = '',
        bool $hideWhenUnavailable = false,
    ): void {
        $this->instanceId = $this->resolveInstanceId($widgetData);
        $this->hideWhenUnavailable = $hideWhenUnavailable;

        if ($widgetReference !== '') {
            // Mounted by Layout Builder: the form comes from the widget's settings.
            $this->hideWhenUnavailable = true;
            $placement = ResolveFormWidgetPlacementAction::run($widgetReference);

            if ($placement instanceof FormWidgetPlacementData) {
                $handle = $placement->handle;
                $this->instanceId = $placement->instanceId;
            }
        }
        $this->fallbackMessage = $this->stringValue($widgetData, 'fallback_message');
        $this->fallbackLabel = $this->stringValue($widgetData, 'fallback_label');
        $this->fallbackUrl = PublicUrlSanitizer::sanitize($widgetData['fallback_url'] ?? null);

        $resolvedHandle = $handle ?? $this->resolveHandle($widgetData);
        $this->formHandle = is_int($resolvedHandle) || is_string($resolvedHandle)
            ? trim((string) $resolvedHandle)
            : '';

        // Deferred Livewire requests do not run the original frontend pipeline.
        // Retain only a consistency reference; subsequent requests resolve
        // their own site before allowing the form to render or submit.
        $site = $this->currentSite();
        if ($site instanceof Site) {
            $this->siteReference = Crypt::encryptString((string) $site->id);
        }
    }

    public function loadForm(): void
    {
        if ($this->formReference !== '' || $this->formHandle === '') {
            return;
        }

        $this->loadAttempted = true;

        $form = $this->resolveFormForCurrentSite($this->formHandle);

        if ($form instanceof Form) {
            $this->formReference = ResolveFormComponentFormAction::referenceFor($form);
        }
    }

    public function render(): View
    {
        if ($this->formReference !== '' && $this->resolveSiteId() === null) {
            $this->formReference = '';
        }

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

        $siteId = $this->resolveSiteId();
        if ($siteId === null) {
            return null;
        }

        return FindActiveSiteFormAction::run($siteId, $handle);
    }

    private function resolveSiteId(): ?int
    {
        try {
            $siteId = Crypt::decryptString($this->siteReference);
        } catch (Throwable) {
            return null;
        }

        if (! ctype_digit($siteId) || (int) $siteId < 1) {
            return null;
        }

        $currentSite = ResolveFormRequestSiteAction::run();
        if (! $currentSite instanceof Site || $currentSite->id !== (int) $siteId) {
            return null;
        }

        return $currentSite->id;
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

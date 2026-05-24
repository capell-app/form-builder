<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Data\MarketingStudioActionData;
use Capell\Admin\Enums\MarketingStudioSectionEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Actions\RegisterBlazeOptimizedViewsAction;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Data\VendorAssetData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Enums\ResourceEnum;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Policies\SubmissionPolicy;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;

class FormBuilderServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-form-builder';

    public static string $packageName = 'capell-app/form-builder';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasViews(self::$name)
            ->hasTranslations()
            ->hasMigrations([
                '2026_05_10_190849_01_create_form-builder_table',
                '2026_05_10_190849_02_create_submissions_table',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->bootInstalledPackage();
        });
    }

    public function packageBooted(): void
    {
        Gate::policy(Submission::class, SubmissionPolicy::class);

        if (! $this->isPackageInstalled()) {
            return;
        }

        Relation::morphMap([
            'form' => Form::class,
            'form_submission' => Submission::class,
        ], merge: true);
    }

    protected function isPackageInstalled(): bool
    {
        return CapellCore::getPackage(static::$packageName)->isInstalled();
    }

    protected function isLivewireV3(): bool
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('livewire/livewire')) {
            return true;
        }

        $version = InstalledVersions::getVersion('livewire/livewire');

        return version_compare($version, '4.0.0', '<');
    }

    private function bootInstalledPackage(): self
    {
        return $this
            ->registerModels()
            ->registerPackageAssets()
            ->registerBlazeComponents()
            ->registerRenderables()
            ->registerResources()
            ->registerMarketingStudioActions()
            ->registerLivewireComponents()
            ->registerBladeComponents();
    }

    private function registerModels(): self
    {
        CapellCore::registerModels([
            Form::class,
            Submission::class,
        ]);

        return $this;
    }

    private function registerPackageAssets(): self
    {
        CapellCore::registerVendorAsset(
            VendorAssetData::tailwindSource('resources/views/**/*.blade.php', static::$packageName),
        );

        return $this;
    }

    private function registerBlazeComponents(): self
    {
        RegisterBlazeOptimizedViewsAction::run(__DIR__ . '/../../resources/views/components');

        return $this;
    }

    private function registerResources(): self
    {
        foreach (ResourceEnum::cases() as $resource) {
            if (! class_exists($resource->value)) {
                continue;
            }

            CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
                class: $resource->value,
                group: $resource->name,
            ));
        }

        return $this;
    }

    private function registerMarketingStudioActions(): self
    {
        CapellAdmin::registerMarketingStudioAction(new MarketingStudioActionData(
            key: 'form-builder.submissions',
            label: fn (): string => __('capell-form-builder::navigation.submissions'),
            url: fn (): string => SubmissionResource::getUrl(),
            section: MarketingStudioSectionEnum::Forms,
            icon: 'heroicon-o-inbox-stack',
            sort: 10,
        ));

        return $this;
    }

    private function registerRenderables(): self
    {
        resolve(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: 'capell-form-builder::block.form',
            type: 'layout-block',
            livewire: FormComponent::class,
        ));

        resolve(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: LivewireComponentEnum::FormElement->value,
            type: 'form-field',
            livewire: FormElementComponent::class,
        ));

        return $this;
    }

    private function registerLivewireComponents(): self
    {
        if ($this->isLivewireV3()) {
            foreach (LivewireComponentEnum::getComponents() as $name => $component) {
                if ($component === null) {
                    continue;
                }

                if (! class_exists($component)) {
                    continue;
                }

                Livewire::component($name, $component);
            }
        } else {
            Livewire::addNamespace(
                namespace: 'capell-form-builder',
                classNamespace: 'Capell\\FormBuilder\\Livewire',
                classPath: __DIR__ . '/../Livewire',
                classViewPath: __DIR__ . '/../../resources/views/livewire',
            );
        }

        return $this;
    }

    private function registerBladeComponents(): self
    {
        Blade::componentNamespace('Capell\\FormBuilder\\View\\Components', 'capell-form-builder');
        Blade::anonymousComponentNamespace('Capell\\FormBuilder\\View\\Components');

        return $this;
    }
}

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
use Capell\FormBuilder\Actions\BuildFormSubmissionPrivacyExportAction;
use Capell\FormBuilder\Actions\EraseFormSubmissionPrivacyDataAction;
use Capell\FormBuilder\Actions\InstallThemeDemoFormsAction;
use Capell\FormBuilder\Console\Commands\ExportSubmissionsCommand;
use Capell\FormBuilder\Console\Commands\PruneExpiredFormSubmissionsCommand;
use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;
use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Enums\ResourceEnum;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Policies\FormPolicy;
use Capell\FormBuilder\Policies\SubmissionPolicy;
use Capell\FormBuilder\Support\DnsFormBuilderWebhookHostResolver;
use Capell\FormBuilder\Support\SpamProtection\NullSpamProtectionProvider;
use Composer\InstalledVersions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Override;
use Spatie\LaravelPackageTools\Package;

final class FormBuilderServiceProvider extends AbstractPackageServiceProvider
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
            ->hasRoute('payments')
            ->hasCommand(ExportSubmissionsCommand::class)
            ->hasCommand(PruneExpiredFormSubmissionsCommand::class)
            ->hasMigrations([
                '2026_05_10_190849_01_create_form-builder_table',
                '2026_05_10_190849_02_create_submissions_table',
                '2026_07_12_000001_add_retention_to_submissions_table',
            ]);
    }

    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this->app->singleton(FormBuilderWebhookHostResolver::class, DnsFormBuilderWebhookHostResolver::class);
        $this->app->singleton(SpamProtectionProvider::class, function (): SpamProtectionProvider {
            $provider = config('capell-form-builder.spam_protection.provider', NullSpamProtectionProvider::class);

            if (! is_string($provider) || ! class_exists($provider)) {
                return new NullSpamProtectionProvider;
            }

            $instance = $this->app->make($provider);

            return $instance instanceof SpamProtectionProvider
                ? $instance
                : new NullSpamProtectionProvider;
        });
        $this->registerModels();
    }

    public function packageBooted(): void
    {
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);

        if (! $this->isPackageInstalled()) {
            return;
        }

        Relation::morphMap([
            'form' => Form::class,
            'form_submission' => Submission::class,
        ], merge: true);

        if (config('capell-form-builder.retention.schedule_enabled', true) === true) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->command('capell:form-builder:prune')->daily()->withoutOverlapping();
            });
        }
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::getPackage(self::$packageName)->isInstalled();
    }

    #[Override]
    protected function isLivewireV3(): bool
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('livewire/livewire')) {
            return true;
        }

        $version = InstalledVersions::getVersion('livewire/livewire');

        if (! is_string($version)) {
            return true;
        }

        return version_compare($version, '0.0.0', '<');
    }

    #[Override]
    protected function bootInstalledPackage(): self
    {
        return $this
            ->registerPackageAssets()
            ->registerBlazeComponents()
            ->registerRenderables()
            ->registerResources()
            ->registerMarketingStudioActions()
            ->registerPrivacyCenterContributors()
            ->registerPackageLivewireComponents()
            ->registerBladeComponents()
            ->registerThemeDemoForms();
    }

    private function registerThemeDemoForms(): self
    {
        Event::listen(
            'capell.theme-demo.forms',
            static function (int|string $siteId, string $formsPayload): void {
                InstallThemeDemoFormsAction::run(
                    siteId: $siteId,
                    formsPayload: $formsPayload,
                );
            },
        );

        return $this;
    }

    private function registerPrivacyCenterContributors(): self
    {
        $eraserRegistryClass = implode('\\', ['Capell', 'PrivacyCenter', 'Support', 'PrivacySubjectEraserRegistry']);
        $exporterRegistryClass = implode('\\', ['Capell', 'PrivacyCenter', 'Support', 'PrivacySubjectExporterRegistry']);

        if (class_exists($eraserRegistryClass) && $this->app->bound($eraserRegistryClass)) {
            $registry = $this->app->make($eraserRegistryClass);

            if (is_object($registry) && method_exists($registry, 'register')) {
                $registry->register('form-builder', static fn (Model $subject): int => EraseFormSubmissionPrivacyDataAction::run($subject));
            }
        }

        if (class_exists($exporterRegistryClass) && $this->app->bound($exporterRegistryClass)) {
            $registry = $this->app->make($exporterRegistryClass);

            if (is_object($registry) && method_exists($registry, 'register')) {
                $registry->register('form-builder', static fn (Model $subject): array => BuildFormSubmissionPrivacyExportAction::run($subject));
            }
        }

        return $this;
    }

    private function registerModels(): self
    {
        $this->surface()->models([
            Form::class,
            Submission::class,
        ]);

        return $this;
    }

    private function registerPackageAssets(): self
    {
        CapellCore::registerVendorAsset(
            VendorAssetData::tailwindSource('resources/views/**/*.blade.php', self::$packageName),
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
            key: 'form-builder.forms',
            label: fn (): string => __('capell-form-builder::navigation.forms'),
            url: fn (): string => FormResource::getUrl(),
            section: MarketingStudioSectionEnum::Forms,
            icon: 'heroicon-o-clipboard-document-list',
            sort: 9,
        ));

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

    private function registerPackageLivewireComponents(): self
    {
        Livewire::component(LivewireComponentEnum::PublicFormFields->value, FormComponent::class);
        Livewire::component(LivewireComponentEnum::PublicForm->value, FormElementComponent::class);

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

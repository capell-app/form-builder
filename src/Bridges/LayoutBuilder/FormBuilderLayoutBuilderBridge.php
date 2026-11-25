<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Bridges\LayoutBuilder;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\PackageSurfaceRegistrar;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\LayoutBuilder\InvalidateFormWidgetPagesObserver;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Models\Form;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetAvailability;
use Capell\LayoutBuilder\Enums\ComponentTypeEnum as LayoutBuilderComponentTypeEnum;
use Capell\LayoutBuilder\Enums\ConfiguratorTypeEnum as LayoutBuilderConfiguratorTypeEnum;
use Illuminate\Contracts\Foundation\Application;

/** Registers the form widget with Layout Builder when Layout Builder is available and installed. */
final class FormBuilderLayoutBuilderBridge
{
    public static function register(Application $app): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/layout-builder')) {
            return;
        }

        $app->make(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: LivewireComponentEnum::LayoutWidget->value,
            type: 'layout-widget',
            livewire: FormElementComponent::class,
        ));

        $app->make(PackageSurfaceRegistrar::class)->components(
            LayoutBuilderComponentTypeEnum::Widget->name,
            ['FormBuilderForm' => LivewireComponentEnum::LayoutWidget->value],
        );

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
            class: FormBuilderFormWidgetConfigurator::class,
            group: LayoutBuilderConfiguratorTypeEnum::Widget->value,
            name: FormBuilderFormWidgetConfigurator::getKey(),
        ));

        // One instance per request, so its per-render lookups never outlive the request.
        $app->scoped(FormWidgetAvailability::class);
        $app->tag([FormWidgetAvailability::class], PublicLayoutWidgetAvailability::TAG);
        Form::observe(InvalidateFormWidgetPagesObserver::class);
    }
}

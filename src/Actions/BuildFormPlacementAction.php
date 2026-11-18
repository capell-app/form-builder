<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Contracts\Capabilities\LayoutWidgetHost;
use Capell\Core\Models\Site;
use Capell\Core\Support\Permissions\SiteAccess;
use Capell\FormBuilder\Data\FormPlacementData;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildFormPlacementAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly LayoutWidgetHost $host) {}

    public function handle(Form $form): FormPlacementData
    {
        Gate::authorize('update', $form);
        abort_unless(SiteAccess::current()->canSiteId((int) $form->site_id), 403);
        $site = Site::query()->findOrFail($form->site_id);

        return new FormPlacementData(
            handle: $form->is_active ? $form->handle : null,
            siteName: $site->name,
            submissionsUrl: SubmissionResource::canViewAny()
                ? SubmissionResource::getUrl('index', ['filters' => ['form_id' => ['value' => $form->getKey()]]])
                : null,
            storesSubmissions: $form->settings->storeSubmissions ?? true,
            layoutBuilderAvailable: $this->layoutBuilderAvailable(),
        );
    }

    /**
     * Editors can place the form themselves only when the layout package offers
     * the Form Builder form widget component.
     */
    private function layoutBuilderAvailable(): bool
    {
        return $this->host->offersComponent(LivewireComponentEnum::LayoutWidget->value);
    }
}

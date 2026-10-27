<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Models\Site;
use Capell\Core\Support\Permissions\SiteAccess;
use Capell\FormBuilder\Data\FormPlacementData;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildFormPlacementAction
{
    use AsFake;
    use AsObject;

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
        );
    }
}

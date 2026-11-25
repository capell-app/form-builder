<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support\Privacy;

use Capell\Core\Contracts\Privacy\RetainsSiteRecords;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Override;

/** Stops a site being permanently deleted while its submissions, or its forms' submissions, are under legal hold. */
final class FormBuilderSiteRecordRetainer implements RetainsSiteRecords
{
    #[Override]
    public function retainedRecords(Site $site): array
    {
        $held = Submission::query()
            ->where('legal_hold', true)
            ->where(static fn (Builder $query): Builder => $query
                ->where('site_id', $site->getKey())
                ->orWhereHas('form', static fn (Builder $form): Builder => $form->where('site_id', $site->getKey())))
            ->count();

        if ($held < 1) {
            return [];
        }

        return [trans_choice('capell-form-builder::message.retained_site_submissions', $held) => $held];
    }
}

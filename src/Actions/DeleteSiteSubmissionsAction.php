<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Exceptions\SiteHasRetainedRecordsException;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Before a site is permanently deleted, deletes every submission recorded against it or made on one of its forms,
 * through the one delete routine, so their uploads go too. The database refuses to delete a site or form that still
 * has submissions, so none can be removed by a cascade. A held submission, or one whose uploads could not all be
 * deleted, is kept, and so is the site.
 *
 * @method static void run(Site $site)
 */
final class DeleteSiteSubmissionsAction
{
    use AsFake;
    use AsObject;

    /** @throws SiteHasRetainedRecordsException when any submission had to be kept */
    public function handle(Site $site): void
    {
        $query = static fn (): Builder => Submission::query()->where(static fn (Builder $query): Builder => $query
            ->where('site_id', $site->getKey())
            ->orWhereHas('form', static fn (Builder $form): Builder => $form->where('site_id', $site->getKey())));

        $query()->select('id')->chunkById(200, static function ($chunk): void {
            foreach ($chunk->modelKeys() as $id) {
                if (is_numeric($id)) {
                    DeleteSubmissionAction::run((int) $id);
                }
            }
        });

        $remaining = $query()->count();

        if ($remaining === 0) {
            return;
        }

        $held = $query()->where('legal_hold', true)->count();
        $retained = array_filter([
            trans_choice('capell-form-builder::message.retained_site_submissions', $held) => $held,
            trans_choice('capell-form-builder::message.site_submissions_not_deleted', $remaining - $held) => $remaining - $held,
        ]);

        throw SiteHasRetainedRecordsException::forRecords($retained);
    }
}

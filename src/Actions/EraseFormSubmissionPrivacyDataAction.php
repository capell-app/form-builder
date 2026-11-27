<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormSubmissionPrivacyErasureResultData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Deletes a privacy subject's submissions and their uploads.
 *
 * A submission under legal hold is kept, with its uploads: an operator placed the hold because it may be needed
 * for a complaint, claim or investigation, and erasure does not override that. Its id is returned so the privacy
 * request records what was kept and why. Each held submission is marked with when its erasure was asked for, so
 * releasing the hold erases it (see SetSubmissionLegalHoldAction). A submission whose uploads could not all be deleted
 * is kept for retry and returned as failed, so the privacy request is not reported fulfilled while its data remains.
 *
 * @method static FormSubmissionPrivacyErasureResultData run(Model $subject, list<int> $linkedSubmissionIds = [])
 */
final class EraseFormSubmissionPrivacyDataAction
{
    use AsFake;
    use AsObject;

    /** @param  list<int>  $linkedSubmissionIds  submissions an operator confirmed belong to the subject */
    public function handle(Model $subject, array $linkedSubmissionIds = []): FormSubmissionPrivacyErasureResultData
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);
        $ids = array_values(array_unique([...$recordIds->submissionIds, ...$linkedSubmissionIds]));

        $deleted = 0;
        $heldIds = [];
        $failedIds = [];

        // One decision per submission, made on the locked row: a held one is marked to be erased once its hold is
        // released, anything else goes through the one delete routine.
        foreach ($ids as $id) {
            $outcome = DB::transaction(static function () use ($id): ?string {
                $current = Submission::query()->whereKey($id)->lockForUpdate()->first();

                if (! $current instanceof Submission) {
                    return null;
                }

                if ($current->legal_hold) {
                    if ($current->erasure_requested_at === null) {
                        $current->forceFill(['erasure_requested_at' => now()])->save();
                    }

                    return 'held';
                }

                return DeleteSubmissionAction::run($current) ? 'deleted' : 'failed';
            });

            if ($outcome === 'held') {
                $heldIds[] = $id;
            } elseif ($outcome === 'deleted') {
                $deleted++;
            } elseif ($outcome === 'failed') {
                $failedIds[] = $id;
            }
        }

        sort($heldIds);
        sort($failedIds);

        return new FormSubmissionPrivacyErasureResultData($deleted, $heldIds, $failedIds);
    }
}

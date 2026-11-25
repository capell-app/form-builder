<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormSubmissionPrivacyErasureResultData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Deletes a privacy subject's submissions and their uploads.
 *
 * A submission under legal hold is kept, with its uploads: an operator placed the hold because it may be needed
 * for a complaint, claim or investigation, and erasure does not override that. Its id is returned so the privacy
 * request records what was kept and why.
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

        $submissions = Submission::query()->whereKey($ids)->where('legal_hold', false);

        DeleteSubmissionUploadsAction::run(clone $submissions);

        $deleted = $submissions->delete();

        // Read after the delete, so a hold placed while the erasure ran is still reported.
        /** @var list<int> $heldIds */
        $heldIds = array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, Submission::query()->whereKey($ids)->where('legal_hold', true)->orderBy('id')->pluck('id')->all());

        return new FormSubmissionPrivacyErasureResultData(is_int($deleted) ? $deleted : 0, $heldIds);
    }
}

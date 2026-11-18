<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Model $subject, list<int> $linkedSubmissionIds = [])
 */
final class EraseFormSubmissionPrivacyDataAction
{
    use AsFake;
    use AsObject;

    /** @param  list<int>  $linkedSubmissionIds  submissions an operator confirmed belong to the subject */
    public function handle(Model $subject, array $linkedSubmissionIds = []): int
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);

        $submissions = Submission::query()->whereKey(array_values(array_unique([...$recordIds->submissionIds, ...$linkedSubmissionIds])));

        DeleteSubmissionUploadsAction::run($submissions);

        $deleted = $submissions->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}

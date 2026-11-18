<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static bool run(Submission $submission)
 */
final class DeleteSubmissionAction
{
    use AsFake;
    use AsObject;

    /**
     * Deletes the submission and its stored uploads. A submission under legal hold is never deleted here.
     */
    public function handle(Submission $submission): bool
    {
        if ($submission->legal_hold) {
            return false;
        }

        DeleteSubmissionUploadsAction::run(Submission::query()->whereKey($submission->getKey()));

        return (bool) $submission->delete();
    }
}

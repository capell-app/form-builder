<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Model $subject)
 */
final class EraseFormSubmissionPrivacyDataAction
{
    use AsFake;
    use AsObject;

    public function handle(Model $subject): int
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);

        $deleted = Submission::query()->whereKey($recordIds->submissionIds)->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}

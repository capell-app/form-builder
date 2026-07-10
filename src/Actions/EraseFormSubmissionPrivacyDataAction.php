<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static int run(Model $subject)
 */
final class EraseFormSubmissionPrivacyDataAction
{
    use AsAction;

    public function handle(Model $subject): int
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);

        return Submission::query()->whereKey($recordIds->submissionIds)->delete();
    }
}

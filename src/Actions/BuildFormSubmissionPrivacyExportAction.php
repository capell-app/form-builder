<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, mixed> run(Model $subject)
 */
final class BuildFormSubmissionPrivacyExportAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(Model $subject): array
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);
        $submissions = Submission::query()
            ->whereKey($recordIds->submissionIds)
            ->oldest('id')
            ->get()
            ->map(static fn (Submission $submission): array => Arr::except($submission->toArray(), [
                'id', 'form_id',
            ]))
            ->values()
            ->all();

        return $submissions === [] ? [] : ['submissions' => $submissions];
    }
}

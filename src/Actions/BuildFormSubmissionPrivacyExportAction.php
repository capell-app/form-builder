<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, mixed> run(Model $subject, list<int> $linkedSubmissionIds = [])
 */
final class BuildFormSubmissionPrivacyExportAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  list<int>  $linkedSubmissionIds  submissions an operator confirmed belong to the subject
     * @return array<string, mixed>
     */
    public function handle(Model $subject, array $linkedSubmissionIds = []): array
    {
        $recordIds = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);
        $submissions = Submission::query()
            ->whereKey(array_values(array_unique([...$recordIds->submissionIds, ...$linkedSubmissionIds])))
            ->oldest('id')
            ->get()
            ->map(static fn (Submission $submission): array => Arr::except($submission->toArray(), [
                'id', 'form_id', 'subject_email_hash', 'delivery_pending_at', 'delivery_failed_at',
                'delivery_attempts', 'notification_queued_at', 'autoresponder_queued_at',
            ]))
            ->values()
            ->all();

        return $submissions === [] ? [] : ['submissions' => $submissions];
    }
}

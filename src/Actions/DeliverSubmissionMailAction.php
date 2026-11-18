<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Settles a submission's pending notification and autoresponder.
 *
 * The pending marker is saved with the submission, so a crash or queue outage after the save is
 * never lost: each channel is queued at most once, and the marker is cleared only when both are
 * settled. A failure is recorded for operators and retried by the recovery command.
 *
 * @method static bool run(Submission $submission)
 */
final class DeliverSubmissionMailAction
{
    use AsFake;
    use AsObject;

    public function handle(Submission $submission): bool
    {
        if ($submission->delivery_pending_at === null) {
            return true;
        }

        $notified = SendSubmissionNotificationAction::run($submission);
        $responded = SendSubmissionAutoresponderAction::run($submission);

        if ($notified && $responded) {
            $submission->forceFill(['delivery_pending_at' => null, 'delivery_failed_at' => null])->save();

            return true;
        }

        $submission->forceFill([
            'delivery_failed_at' => now(),
            'delivery_attempts' => $submission->delivery_attempts + 1,
        ])->save();

        return false;
    }
}

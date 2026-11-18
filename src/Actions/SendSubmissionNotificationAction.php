<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

class SendSubmissionNotificationAction
{
    use AsFake;
    use AsObject;

    /**
     * Queues the notification once. Returns false only when queueing failed, so the delivery stays pending for recovery.
     */
    public function handle(Submission $submission): bool
    {
        if ($submission->status === SubmissionStatus::Spam || $submission->notification_queued_at !== null) {
            return true;
        }

        $submission->loadMissing('form');

        $notificationEmail = $submission->form->settings?->notificationEmail;

        $recipients = ParseNotificationRecipientsAction::run(is_string($notificationEmail) ? $notificationEmail : null);

        if ($recipients === []) {
            return true;
        }

        try {
            Mail::to($recipients)->queue(new FormSubmissionNotificationMail(
                submission: $submission,
                replyToAddress: ResolveSubmissionReplyAddressAction::run($submission),
            ));
        } catch (Throwable $throwable) {
            Log::warning('Failed to queue form submission notification.', [
                'submission_id' => $submission->getKey(),
                'exception' => $throwable::class,
            ]);

            return false;
        }

        // Recorded outside the try, so a failed save is never mistaken for a failed enqueue and sent twice.
        $submission->forceFill(['notification_queued_at' => now()])->save();

        return true;
    }
}

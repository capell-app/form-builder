<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SendSubmissionNotificationAction
{
    use AsAction;

    public function handle(Submission $submission): void
    {
        $submission->loadMissing('form');

        $notificationEmail = $submission->form?->settings?->notificationEmail;

        if (! is_string($notificationEmail) || trim($notificationEmail) === '') {
            return;
        }

        try {
            Mail::to($notificationEmail)->queue(new FormSubmissionNotificationMail(
                submission: $submission,
                replyToAddress: ResolveSubmissionReplyAddressAction::run($submission),
            ));
        } catch (Throwable $throwable) {
            Log::warning('Failed to queue form submission notification.', [
                'submission_id' => $submission->getKey(),
                'exception' => $throwable,
            ]);
        }
    }
}

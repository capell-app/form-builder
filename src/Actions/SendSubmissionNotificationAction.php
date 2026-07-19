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

    public function handle(Submission $submission): void
    {
        if ($submission->status === SubmissionStatus::Spam) {
            return;
        }

        $submission->loadMissing('form');

        $notificationEmail = $submission->form->settings?->notificationEmail;

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
                'exception' => $throwable::class,
            ]);
        }
    }
}

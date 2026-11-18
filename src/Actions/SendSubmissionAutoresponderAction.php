<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

class SendSubmissionAutoresponderAction
{
    use AsFake;
    use AsObject;

    /**
     * Queues the autoresponder once. Returns false only when queueing failed, so the delivery stays pending for recovery.
     */
    public function handle(Submission $submission): bool
    {
        if ($submission->autoresponder_queued_at !== null) {
            return true;
        }

        $submission->loadMissing('form');

        $settings = $submission->form->settings;
        $subject = is_string($settings?->autoresponderSubject) ? trim($settings->autoresponderSubject) : '';
        $body = is_string($settings?->autoresponderBody) ? trim($settings->autoresponderBody) : '';

        if ($subject === '' || $body === '') {
            return true;
        }

        $recipient = ResolveSubmissionReplyAddressAction::run($submission);

        if ($recipient === null) {
            return true;
        }

        try {
            Mail::to($recipient)->queue(new FormSubmissionAutoresponderMail(
                subjectLine: $subject,
                messageBody: $body,
            ));
        } catch (Throwable $throwable) {
            Log::warning('Failed to queue form submission autoresponder.', [
                'submission_id' => $submission->getKey(),
                'form_id' => $submission->form_id,
                'exception' => $throwable::class,
            ]);

            return false;
        }

        // Recorded outside the try, so a failed save is never mistaken for a failed enqueue and sent twice.
        $submission->forceFill(['autoresponder_queued_at' => now()])->save();

        return true;
    }
}

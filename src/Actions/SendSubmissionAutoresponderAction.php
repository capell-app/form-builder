<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SendSubmissionAutoresponderAction
{
    use AsAction;

    public function handle(Submission $submission): void
    {
        $submission->loadMissing('form');

        $settings = $submission->form->settings;
        $subject = is_string($settings?->autoresponderSubject) ? trim($settings->autoresponderSubject) : '';
        $body = is_string($settings?->autoresponderBody) ? trim($settings->autoresponderBody) : '';

        if ($subject === '' || $body === '') {
            return;
        }

        $recipient = ResolveSubmissionReplyAddressAction::run($submission);

        if ($recipient === null) {
            return;
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
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}

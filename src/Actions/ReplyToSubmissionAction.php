<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Mail\SubmissionReplyMail;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ReplyToSubmissionAction
{
    use AsAction;

    public function handle(Submission $submission, string $subject, string $message): void
    {
        $replyAddress = ResolveSubmissionReplyAddressAction::run($submission);

        if ($replyAddress === null) {
            throw ValidationException::withMessages([
                'recipient' => __('capell-form-builder::message.reply_recipient_missing'),
            ]);
        }

        Mail::to($replyAddress)->send(new SubmissionReplyMail(
            submission: $submission,
            subjectLine: $subject,
            messageBody: $message,
        ));

        if ($submission->status === SubmissionStatus::New) {
            $submission->forceFill(['status' => SubmissionStatus::Read])->save();
        }
    }
}

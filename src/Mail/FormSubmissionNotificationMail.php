<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Mail;

use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Queue\Middleware\SuppressInactivePostmarkRecipientFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmissionNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public ?string $replyToAddress = null,
    ) {
        $this->afterCommit();
        $this->through([new SuppressInactivePostmarkRecipientFailure]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->replyToAddress !== null ? [new Address($this->replyToAddress)] : [],
            subject: (string) __('capell-form-builder::message.notification_subject', [
                'form' => $this->submission->form->name ?? __('capell-form-builder::generic.form'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'capell-form-builder::mail.submission-notification');
    }
}

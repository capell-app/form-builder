<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Mail;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public string $subjectLine,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'capell-form-builder::mail.submission-reply');
    }
}

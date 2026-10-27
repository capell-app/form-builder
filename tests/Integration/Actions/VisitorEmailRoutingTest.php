<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\ReplyToSubmissionAction;
use Capell\FormBuilder\Actions\SendSubmissionAutoresponderAction;
use Capell\FormBuilder\Actions\SendSubmissionNotificationAction;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Mail\SubmissionReplyMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Mail;

it('uses the visitor selection consistently across replies notifications and autoresponders', function (array $values, string $expected): void {
    Mail::fake();
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'colleague', 'label' => 'Colleague', 'type' => 'email'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
        ],
        'settings' => [
            'visitor_email_field_key' => 'visitor',
            'notification_email' => 'owner@example.com',
            'autoresponder_subject' => 'Thank you',
            'autoresponder_body' => 'We received your enquiry.',
        ],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => $values],
        'status' => SubmissionStatus::New,
    ]);

    SendSubmissionNotificationAction::run($submission);
    SendSubmissionAutoresponderAction::run($submission);
    ReplyToSubmissionAction::run($submission, 'Your enquiry', 'Here is our reply.');

    Mail::assertSent(SubmissionReplyMail::class, fn (SubmissionReplyMail $mail): bool => $mail->hasTo($expected));
    Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $mail): bool => $mail->hasTo('owner@example.com')
        && $mail->replyToAddress === $expected
        && $mail->envelope()->replyTo[0]->address === $expected);
    Mail::assertQueued(FormSubmissionAutoresponderMail::class, fn (FormSubmissionAutoresponderMail $mail): bool => $mail->hasTo($expected));

    // Form edits must not change the resolved addresses of mail already queued.
    $form->update(['settings' => ['visitor_email_field_key' => 'colleague']]);
    Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $mail): bool => $mail->replyToAddress === $expected);
    Mail::assertQueued(FormSubmissionAutoresponderMail::class, fn (FormSubmissionAutoresponderMail $mail): bool => $mail->hasTo($expected));
})->with([
    'selected second Email field' => [['colleague' => 'colleague@example.com', 'visitor' => 'visitor@example.com'], 'visitor@example.com'],
    'earlier submission lacks selected answer' => [['colleague' => 'colleague@example.com'], 'colleague@example.com'],
]);

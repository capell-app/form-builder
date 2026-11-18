<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Health\FormBuilderHealthCheck;
use Capell\FormBuilder\Mail\FormSubmissionAutoresponderMail;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;

function deliveryRecoveryForm(): Form
{
    return Form::factory()->create([
        'settings' => [
            'store_submissions' => true,
            'notification_email' => 'team@example.test',
            'autoresponder_subject' => 'Thanks',
            'autoresponder_body' => 'We received your message.',
        ],
        'schema' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
        ],
    ]);
}

it('recovers a submission whose mail failed to queue after it was saved, without sending anything twice', function (): void {
    Event::fake([FormSubmitted::class]);
    $form = deliveryRecoveryForm();

    // The notification queues, then the queue goes down before the autoresponder.
    $fake = new MailFake(app('mail.manager'));
    $calls = 0;
    Mail::shouldReceive('to')->andReturnUsing(function (mixed $recipients) use ($fake, &$calls) {
        $calls++;

        throw_if($calls === 2, new RuntimeException('Queue unavailable'));

        return $fake->to($recipients);
    });

    $submission = CreateSubmissionAction::run($form, ['email' => 'visitor@example.test'], new SubmissionMetaData);
    $submission->refresh();

    expect($submission->exists)->toBeTrue()
        ->and($submission->delivery_pending_at)->not->toBeNull()
        ->and($submission->delivery_failed_at)->not->toBeNull()
        ->and($submission->delivery_attempts)->toBe(1)
        ->and($submission->notification_queued_at)->not->toBeNull()
        ->and($submission->autoresponder_queued_at)->toBeNull()
        ->and(FormBuilderHealthCheck::passed())->toBeFalse();

    $this->travel(10)->minutes();
    $this->artisan('capell:form-builder:recover-deliveries')->assertSuccessful();
    $this->artisan('capell:form-builder:recover-deliveries')->assertSuccessful();

    $submission->refresh();

    expect($submission->delivery_pending_at)->toBeNull()
        ->and($submission->delivery_failed_at)->toBeNull()
        ->and($submission->autoresponder_queued_at)->not->toBeNull();
    $fake->assertQueuedCount(2);
    $fake->assertQueued(FormSubmissionNotificationMail::class, 1);
    $fake->assertQueued(FormSubmissionAutoresponderMail::class, 1);
});

it('leaves a submission pending and reports failure while the queue stays down', function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Queue unavailable'));

    $submission = CreateSubmissionAction::run(deliveryRecoveryForm(), ['email' => 'visitor@example.test'], new SubmissionMetaData);

    $this->travel(10)->minutes();
    $this->artisan('capell:form-builder:recover-deliveries')->assertFailed();

    $submission->refresh();

    expect($submission->delivery_pending_at)->not->toBeNull()
        ->and($submission->delivery_attempts)->toBe(2)
        ->and(FormBuilderHealthCheck::runDiagnostics()->firstWhere('id', 'form.builder.submission.delivery')?->passed)->toBeFalse();
});

it('does not retry a delivery that is still inside its grace period', function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::fake();

    $submission = Submission::factory()->for(deliveryRecoveryForm())->create([
        'payload' => ['values' => ['email' => 'visitor@example.test']],
    ]);
    $submission->forceFill(['delivery_pending_at' => now()])->save();

    $this->artisan('capell:form-builder:recover-deliveries')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($submission->refresh()->delivery_pending_at)->not->toBeNull();
});

it('clears the delivery intent once both emails are queued', function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::fake();

    $submission = CreateSubmissionAction::run(deliveryRecoveryForm(), ['email' => 'visitor@example.test'], new SubmissionMetaData);

    expect($submission->refresh()->delivery_pending_at)->toBeNull()
        ->and(FormBuilderHealthCheck::runDiagnostics()->firstWhere('id', 'form.builder.submission.delivery')?->passed)->toBeTrue();
    Mail::assertQueued(FormSubmissionNotificationMail::class, 1);
    Mail::assertQueued(FormSubmissionAutoresponderMail::class, 1);
});

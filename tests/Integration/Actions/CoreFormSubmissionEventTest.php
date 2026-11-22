<?php

declare(strict_types=1);

use Capell\Core\Events\Capabilities\FormSubmissionReceived;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/** @return Form a form with one required email field */
function coreEventEmailForm(): Form
{
    return Form::factory()->create([
        'handle' => 'contact',
        'name' => 'Contact us',
        'schema' => [['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
    ]);
}

it('announces a stored submission through the Core form submission event', function (): void {
    Event::fake([FormSubmissionReceived::class]);
    Queue::fake();
    $form = coreEventEmailForm();

    $submission = CreateSubmissionAction::run($form, ['email' => 'person@example.test'], new SubmissionMetaData(userAgent: 'Capell Test Browser', url: 'https://example.test/contact', referer: 'https://example.test/pricing'));

    Event::assertDispatchedTimes(FormSubmissionReceived::class, 1);
    Event::assertDispatched(FormSubmissionReceived::class, function (FormSubmissionReceived $event) use ($form, $submission): bool {
        expect($event->submission->form->id)->toBe($form->getKey())
            ->and($event->submission->form->key)->toBe('contact')
            ->and($event->submission->form->name)->toBe('Contact us')
            ->and($event->submission->form->siteId)->toBe($form->site_id)
            ->and($event->submission->submissionId)->toBe($submission->getKey())
            ->and($event->submission->values)->toBe(['email' => 'person@example.test'])
            ->and($event->submission->sourceUrl)->toBe('https://example.test/contact')
            ->and($event->submission->referer)->toBe('https://example.test/pricing')
            ->and($event->submission->userAgent)->toBe('Capell Test Browser')
            ->and($event->submission->source?->resolve()?->is($submission))->toBeTrue();

        return true;
    });
});

it('announces an unstored submission without a submission id', function (): void {
    Event::fake([FormSubmissionReceived::class]);
    $form = coreEventEmailForm();

    DispatchUnstoredFormSubmissionAction::run($form, ['email' => 'unstored@example.test'], new SubmissionMetaData);

    expect(Submission::query()->count())->toBe(0);
    Event::assertDispatched(FormSubmissionReceived::class, fn (FormSubmissionReceived $event): bool => $event->submission->submissionId === null
        && $event->submission->source === null
        && $event->submission->values === ['email' => 'unstored@example.test']);
});

it('does not announce a rejected submission', function (): void {
    Event::fake([FormSubmissionReceived::class]);
    $form = coreEventEmailForm();

    expect(fn (): Submission => CreateSubmissionAction::run($form, ['email' => 'not-an-email'], new SubmissionMetaData))
        ->toThrow(ValidationException::class);

    Event::assertNotDispatched(FormSubmissionReceived::class);
});

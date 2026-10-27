<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\ResolveSubmissionReplyAddressAction;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;

function makeEmailFieldForm(): Form
{
    return Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
        ],
    ]);
}

it('resolves a reply address for a non-spam submission', function (): void {
    $form = makeEmailFieldForm();

    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['email' => 'customer@example.com']],
        'status' => SubmissionStatus::New,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBe('customer@example.com');
});

it('never resolves a reply address for a spam submission', function (): void {
    $form = makeEmailFieldForm();
    $form->update(['settings' => ['visitor_email_field_key' => 'email']]);

    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['email' => 'attacker@example.com']],
        'status' => SubmissionStatus::Spam,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBeNull();
});

it('prefers the selected stable key over schema order', function (bool $reverse): void {
    $schema = [
        ['key' => 'colleague', 'label' => 'Email', 'type' => 'email'],
        ['key' => 'visitor', 'label' => 'Email', 'type' => 'email'],
    ];
    $form = Form::factory()->create([
        'schema' => $reverse ? array_reverse($schema) : $schema,
        'settings' => ['visitor_email_field_key' => 'visitor'],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['colleague' => 'colleague@example.com', 'visitor' => ' visitor@example.com ']],
        'status' => SubmissionStatus::New,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBe('visitor@example.com');
})->with([false, true]);

it('retains automatic schema order for stored legacy settings without the selection key', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'invalid', 'label' => 'Invalid', 'type' => 'email'],
            ['key' => 'first', 'label' => 'First', 'type' => 'email'],
            ['key' => 'second', 'label' => 'Second', 'type' => 'email'],
        ],
    ]);
    Form::query()->whereKey($form->getKey())->update(['settings' => '{"store_submissions":true}']);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['invalid' => 'invalid', 'second' => 'second@example.com', 'first' => 'first@example.com']],
        'status' => SubmissionStatus::New,
    ]);

    expect($form->refresh()->settings)->toBeInstanceOf(FormSettingsData::class)
        ->and($form->settings->visitorEmailFieldKey)->toBeNull()
        ->and(ResolveSubmissionReplyAddressAction::run($submission))->toBe('first@example.com');
});

it('falls back when the selected answer is unavailable or invalid', function (mixed $value): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'fallback', 'label' => 'Fallback', 'type' => 'email'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
        ],
        'settings' => ['visitor_email_field_key' => 'visitor'],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['fallback' => 'fallback@example.com', 'visitor' => $value]],
        'status' => SubmissionStatus::New,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBe('fallback@example.com');
})->with([null, '', '   ', 'invalid', "visitor@example.com\r\nBcc: attacker@example.com", 42, [['visitor@example.com']]]);

it('safely resolves existing submissions after the selected field changes', function (array $selectedField): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'fallback', 'label' => 'Fallback', 'type' => 'email'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
        ],
        'settings' => ['visitor_email_field_key' => 'visitor'],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['visitor' => 'visitor@example.com', 'fallback' => 'fallback@example.com']],
        'status' => SubmissionStatus::New,
    ]);
    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBe('visitor@example.com');
    $originalPayload = $submission->getRawOriginal('payload');

    $form->update(['schema' => array_merge([
        ['key' => 'fallback', 'label' => 'Fallback', 'type' => 'email'],
    ], $selectedField)]);

    expect(ResolveSubmissionReplyAddressAction::run($submission->refresh()))->toBe('fallback@example.com')
        ->and($submission->getRawOriginal('payload'))->toBe($originalPayload);
})->with([
    'deleted' => [[]],
    'changed to text' => [[['key' => 'visitor', 'label' => 'Visitor', 'type' => 'text']]],
    'renamed key' => [[['key' => 'new_visitor', 'label' => 'Visitor', 'type' => 'email']]],
]);

it('returns no recipient when no current Email field contains a valid address', function (SubmissionStatus $status): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
        ],
        'settings' => ['visitor_email_field_key' => 'visitor'],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['text' => 'text@example.com', 'visitor' => 'invalid', 'deleted' => 'deleted@example.com']],
        'status' => $status,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBeNull();
})->with([SubmissionStatus::New, SubmissionStatus::Spam]);

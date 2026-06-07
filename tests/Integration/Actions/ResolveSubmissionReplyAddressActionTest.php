<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\ResolveSubmissionReplyAddressAction;
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

    $submission = Submission::factory()->for($form)->create([
        'payload' => ['values' => ['email' => 'attacker@example.com']],
        'status' => SubmissionStatus::Spam,
    ]);

    expect(ResolveSubmissionReplyAddressAction::run($submission))->toBeNull();
});

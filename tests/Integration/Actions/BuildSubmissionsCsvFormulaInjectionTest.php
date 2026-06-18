<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildSubmissionsCsvAction;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;

it('neutralises formula injection in user-submitted cell values', function (string $payloadValue, string $expectedCell): void {
    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'schema' => [
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    Submission::factory()->for($form)->create([
        'payload' => ['values' => ['message' => $payloadValue]],
    ]);

    $csv = BuildSubmissionsCsvAction::run($form);

    expect($csv)->toContain($expectedCell)
        ->and($csv)->not->toContain($payloadValue . "\r\n");
})->with([
    'equals formula' => ['=HYPERLINK("http://evil")', "'=HYPERLINK"],
    'plus sign' => ['+1', "'+1"],
    'minus sign' => ['-1', "'-1"],
    'at sign' => ['@x', "'@x"],
    'leading tab' => ["\tx", "'\tx"],
]);

it('does not prefix safe cell values', function (): void {
    $form = Form::factory()->create([
        'name' => 'Contact',
        'handle' => 'contact',
        'schema' => [
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    Submission::factory()->for($form)->create([
        'payload' => ['values' => ['message' => 'Hello world']],
    ]);

    $csv = BuildSubmissionsCsvAction::run($form);

    expect($csv)->toContain('Hello world')
        ->and($csv)->not->toContain("'Hello world");
});

<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\PruneExpiredFormSubmissionsAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Storage;

it('prunes expired submissions and uploads while preserving legal holds and future retention', function (): void {
    Storage::fake('local');
    $form = Form::factory()->create();
    Storage::disk('local')->put('form-builder/submissions/private.txt', 'private');
    $expired = Submission::factory()->for($form)->create([
        'submitted_at' => now()->subDays(400),
        'payload' => new SubmissionPayloadData(['file' => ['disk' => 'local', 'path' => 'form-builder/submissions/private.txt']]),
        'meta' => new SubmissionMetaData,
    ]);
    $held = Submission::factory()->for($form)->create(['submitted_at' => now()->subDays(400), 'legal_hold' => true]);
    $extended = Submission::factory()->for($form)->create(['submitted_at' => now()->subDays(400), 'retention_until' => now()->addDay()]);

    expect(PruneExpiredFormSubmissionsAction::run(365, dryRun: true))->toBe(1)
        ->and(Submission::query()->whereKey($expired->getKey())->exists())->toBeTrue()
        ->and(PruneExpiredFormSubmissionsAction::run(365))->toBe(1)
        ->and(Submission::query()->whereKey($expired->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeTrue()
        ->and(Submission::query()->whereKey($extended->getKey())->exists())->toBeTrue();
    Storage::disk('local')->assertMissing('form-builder/submissions/private.txt');
});

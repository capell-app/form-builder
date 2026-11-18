<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Actions\BuildSubmissionPayloadEntriesAction;
use Capell\FormBuilder\Actions\DispatchSubmissionWebhookAction;
use Capell\FormBuilder\Actions\ParseNotificationRecipientsAction;
use Capell\FormBuilder\Actions\PruneExpiredFormSubmissionsAction;
use Capell\FormBuilder\Actions\SendSubmissionNotificationAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Filament\Resources\Submissions\Pages\ListSubmissions;
use Capell\FormBuilder\Mail\FormSubmissionNotificationMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionSiteAccess;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    SubmissionSiteAccess::flushMemoizedPermissions();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function uploadedSubmission(array $attributes = []): Submission
{
    Storage::fake('local');
    Storage::disk('local')->put('form-builder/submissions/cv.pdf', 'pdf-bytes');

    return Submission::factory()->for(Form::factory()->create())->create([
        'payload' => new SubmissionPayloadData(['cv' => [
            'original_name' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'size' => 9,
            'disk' => 'local',
            'path' => 'form-builder/submissions/cv.pdf',
        ]]),
        'meta' => new SubmissionMetaData,
        ...$attributes,
    ]);
}

it('deletes a submission and its upload from the table', function (): void {
    test()->actingAsAdmin();
    $submission = uploadedSubmission();

    livewire(ListSubmissions::class)
        ->callAction(TestAction::make('delete')->table($submission))
        ->assertNotified(__('capell-form-builder::message.submission_deleted'));

    expect(Submission::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('form-builder/submissions/cv.pdf');
});

it('refuses to delete a submission under legal hold', function (): void {
    test()->actingAsAdmin();
    $submission = uploadedSubmission(['legal_hold' => true]);

    livewire(ListSubmissions::class)
        ->assertActionHidden(TestAction::make('delete')->table($submission));

    expect(Submission::query()->count())->toBe(1);
    Storage::disk('local')->assertExists('form-builder/submissions/cv.pdf');
});

it('bulk deletes submissions but skips those under legal hold', function (): void {
    test()->actingAsAdmin();
    $form = Form::factory()->create();
    $plain = Submission::factory()->for($form)->create();
    $held = Submission::factory()->for($form)->create(['legal_hold' => true]);

    livewire(ListSubmissions::class)
        ->callTableBulkAction('delete', [$plain, $held])
        ->assertHasNoTableBulkActionErrors()
        ->assertNotified(__('capell-form-builder::message.submissions_delete_skipped', ['count' => 1]));

    expect(Submission::query()->whereKey($plain->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeTrue();
});

it('downloads an upload through the authorised admin action without showing the storage path', function (): void {
    test()->actingAsAdmin();
    $submission = uploadedSubmission();

    livewire(ListSubmissions::class)
        ->assertActionVisible(TestAction::make('download_upload')->table($submission))
        ->callAction(TestAction::make('download_upload')->table($submission), data: ['upload' => 'cv'])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('cv.pdf', 'pdf-bytes');

    $entries = BuildSubmissionPayloadEntriesAction::run($submission->loadMissing('form'));
    expect(json_encode($entries))->not->toContain('form-builder/submissions');
});

it('signs the exact webhook body and leaves storage paths out of it', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $form = Form::factory()->create(['settings' => ['webhook_url' => 'https://8.8.8.8/form']]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => new SubmissionPayloadData(['cv' => ['original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size' => 9, 'disk' => 'local', 'path' => 'form-builder/submissions/cv.pdf']]),
        'meta' => new SubmissionMetaData,
    ]);

    expect(DispatchSubmissionWebhookAction::run($submission))->toBeTrue();

    Http::assertSent(function (Request $request) use ($form): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $request->header('X-Capell-Timestamp')[0] . '.' . $request->body(), DispatchSubmissionWebhookAction::signingSecret($form));

        return $request->header('X-Capell-Signature')[0] === $expected
            && ! str_contains($request->body(), 'form-builder\/submissions')
            && str_contains($request->body(), 'cv.pdf');
    });
});

it('never accepts any file type when accepted types are empty', function (): void {
    $form = Form::factory()->create(['schema' => [['key' => 'doc', 'label' => 'Doc', 'type' => FormFieldType::File->value]]]);

    $rules = BuildFormValidationRulesAction::run($form, []);

    expect(implode('|', $rules['doc']))->toContain('mimes:pdf')->not->toContain('php')->not->toContain('html');
});

it('prunes expired submissions together with their uploads', function (): void {
    $submission = uploadedSubmission(['submitted_at' => now()->subDays(400)]);

    expect(PruneExpiredFormSubmissionsAction::run(365))->toBe(1)
        ->and(Submission::query()->whereKey($submission->getKey())->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('form-builder/submissions/cv.pdf');
});

it('emails every notification recipient', function (): void {
    Mail::fake();
    $form = Form::factory()->create(['settings' => ['notification_email' => 'a@example.com, b@example.com; not-an-email']]);
    $submission = Submission::factory()->for($form)->create();

    SendSubmissionNotificationAction::run($submission);

    expect(ParseNotificationRecipientsAction::run('a@example.com, b@example.com; not-an-email'))->toBe(['a@example.com', 'b@example.com']);
    Mail::assertQueued(FormSubmissionNotificationMail::class, fn ($mail): bool => $mail->hasTo('a@example.com') && $mail->hasTo('b@example.com'));
});

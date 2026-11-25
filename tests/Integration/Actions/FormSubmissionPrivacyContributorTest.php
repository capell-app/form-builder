<?php

declare(strict_types=1);

use Capell\Core\Data\Privacy\PrivacyErasureReceiptData;
use Capell\Core\Data\Privacy\PrivacyReviewCandidateData;
use Capell\Core\Data\Privacy\PrivacySubject;
use Capell\Core\Enums\PrivacyErasureMode;
use Capell\Core\Exceptions\SiteHasRetainedRecordsException;
use Capell\Core\Models\Site;
use Capell\Core\Support\Packages\InstalledRuntimeLifecycle;
use Capell\Core\Support\Privacy\PrivacySubjectRegistry;
use Capell\FormBuilder\Actions\BuildFormSubmissionPrivacyExportAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\EraseFormSubmissionPrivacyDataAction;
use Capell\FormBuilder\Actions\ResolveFormSubmissionPrivacyRecordIdsAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Exceptions\FormHasSubmissionsUnderLegalHoldException;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Providers\FormBuilderServiceProvider;
use Capell\FormBuilder\Tests\Fixtures\FormBuilderPrivacyTestSubject;
use Capell\PrivacyCenter\Exceptions\PrivacyManualReviewRequiredException;
use Capell\PrivacyCenter\Support\PrivacySubjectEraserRegistry;
use Capell\PrivacyCenter\Support\PrivacySubjectExporterRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::fake();
});

function registerFormBuilderPrivacyContributor(): void
{
    // Applications enforce a morph map, so the subject record is mapped as an account model would be.
    Relation::morphMap(['form_builder_privacy_test_subject' => FormBuilderPrivacyTestSubject::class]);
    app()->singleton(PrivacySubjectEraserRegistry::class);
    app()->singleton(PrivacySubjectExporterRegistry::class);
    app()->forgetInstance(PrivacySubjectRegistry::class);

    // Activate the package after its optional host integrations are available.
    app()->instance(InstalledRuntimeLifecycle::class, new InstalledRuntimeLifecycle(app()));
    app()->register(FormBuilderServiceProvider::class, force: true);
}

function privacyContactForm(): Form
{
    return Form::factory()->create([
        'settings' => ['store_submissions' => true, 'visitor_email_field_key' => 'email'],
        'schema' => [
            ['key' => 'email', 'label' => 'Your email', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
            ['key' => 'friend_email', 'label' => 'Friend email', 'type' => 'email'],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea'],
        ],
    ]);
}

function privacySubject(string $email): FormBuilderPrivacyTestSubject
{
    return new FormBuilderPrivacyTestSubject(['id' => 123, 'email' => $email]);
}

/** A stored subject: Core's privacy registry only accepts saved records. */
function persistedPrivacySubject(string $email): FormBuilderPrivacyTestSubject
{
    // Applications enforce a morph map, so the subject record is mapped as an account model would be.
    Relation::morphMap(['form_builder_privacy_test_subject' => FormBuilderPrivacyTestSubject::class]);
    if (! Schema::hasTable('form_builder_privacy_test_subjects')) {
        Schema::create('form_builder_privacy_test_subjects', static function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }

    return FormBuilderPrivacyTestSubject::query()->create(['email' => $email]);
}

it("exports and erases the subject's own submissions through the privacy registries", function (): void {
    registerFormBuilderPrivacyContributor();
    $form = privacyContactForm();
    $own = CreateSubmissionAction::run($form, ['email' => 'Privacy@Example.test', 'message' => 'Private detail'], new SubmissionMetaData(ipAddress: '203.0.113.10'));
    $other = CreateSubmissionAction::run($form, ['email' => 'other@example.test'], new SubmissionMetaData);

    $subject = persistedPrivacySubject('privacy@example.test');
    $export = resolve(PrivacySubjectExporterRegistry::class)->export($subject);
    $affectedRecords = resolve(PrivacySubjectEraserRegistry::class)->anonymize($subject);

    expect($export['form-builder']['submissions'])->toHaveCount(1)
        ->and(data_get($export, 'form-builder.submissions.0.payload.values.message'))->toBe('Private detail')
        ->and(data_get($export, 'form-builder.submissions.0'))->not->toHaveKeys(['subject_email_hash', 'delivery_pending_at', 'notification_queued_at'])
        ->and($affectedRecords)->toBe(1)
        ->and(Submission::query()->whereKey($own->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($other->getKey())->exists())->toBeTrue();
});

it("never exports or erases another person's submission that names the subject as friend_email", function (): void {
    $form = privacyContactForm();
    $someoneElses = CreateSubmissionAction::run($form, [
        'email' => 'sender@example.test',
        'friend_email' => 'privacy@example.test',
        'message' => "The sender's own words",
    ], new SubmissionMetaData);

    $subject = privacySubject('privacy@example.test');
    $records = ResolveFormSubmissionPrivacyRecordIdsAction::run($subject);

    expect($records->submissionIds)->toBe([])
        ->and($records->reviewSubmissionIds)->toHaveKeys([$someoneElses->id])->toHaveCount(1)
        ->and(BuildFormSubmissionPrivacyExportAction::run($subject))->toBe([])
        ->and(EraseFormSubmissionPrivacyDataAction::run($subject)->deleted)->toBe(0)
        ->and(Submission::query()->whereKey($someoneElses->getKey())->exists())->toBeTrue();

    // The sender's own request still finds it.
    expect(ResolveFormSubmissionPrivacyRecordIdsAction::run(privacySubject('sender@example.test'))->submissionIds)
        ->toBe([$someoneElses->getKey()]);
});

it('routes a mention of the subject to review and holds the request', function (): void {
    registerFormBuilderPrivacyContributor();
    CreateSubmissionAction::run(privacyContactForm(), [
        'email' => 'sender@example.test',
        'friend_email' => 'privacy@example.test',
    ], new SubmissionMetaData);

    $subject = persistedPrivacySubject('privacy@example.test');

    expect(fn (): int => resolve(PrivacySubjectEraserRegistry::class)->anonymize($subject))
        ->toThrow(PrivacyManualReviewRequiredException::class);

    expect(Submission::query()->count())->toBe(1);
});

it('reports a submission whose address sits under an "address" key as a review candidate', function (): void {
    // Saved before ownership was recorded, from a form whose email field was keyed "address".
    $legacy = Submission::factory()->create([
        'payload' => new SubmissionPayloadData(['address' => 'privacy@example.test', 'message' => 'Hello']),
    ]);

    registerFormBuilderPrivacyContributor();

    $candidates = resolve(PrivacySubjectRegistry::class)
        ->reviewCandidates(PrivacySubject::forModel(persistedPrivacySubject('privacy@example.test')));

    $candidate = $candidates[0] ?? null;

    expect($candidates)->toHaveCount(1)
        ->and($candidate)->toBeInstanceOf(PrivacyReviewCandidateData::class);
    assert($candidate instanceof PrivacyReviewCandidateData);
    expect($candidate->candidateId)->toBe($legacy->getKey());
    expect($candidate->candidateType)->toBe('form_submission');
    expect($candidate->reason)->toBe('email_match');
});

it('keeps ownership recorded at submission time when the form schema later changes', function (): void {
    $form = privacyContactForm();
    $own = CreateSubmissionAction::run($form, ['email' => 'privacy@example.test'], new SubmissionMetaData);

    // The email field is later renamed and retyped; the stored owner still decides.
    $form->forceFill(['schema' => [['key' => 'contact', 'label' => 'Contact', 'type' => 'text']]])->save();

    $records = ResolveFormSubmissionPrivacyRecordIdsAction::run(privacySubject('privacy@example.test'));

    expect($records->submissionIds)->toBe([$own->getKey()])
        ->and($records->reviewSubmissionIds)->toBe([]);
});

it('includes a reviewed submission once an operator links it to the subject', function (): void {
    $legacy = Submission::factory()->create([
        'payload' => new SubmissionPayloadData(['address' => 'privacy@example.test']),
    ]);
    $subject = privacySubject('privacy@example.test');

    expect(BuildFormSubmissionPrivacyExportAction::run($subject, [$legacy->getKey()])['submissions'])->toHaveCount(1)
        ->and(EraseFormSubmissionPrivacyDataAction::run($subject, [$legacy->getKey()])->deleted)->toBe(1)
        ->and(Submission::query()->whereKey($legacy->getKey())->exists())->toBeFalse();
});

it('keeps the subject\'s submissions under legal hold, with their uploads, and names them on the erasure receipt', function (): void {
    Storage::fake('local');
    registerFormBuilderPrivacyContributor();
    $form = privacyContactForm();
    $own = CreateSubmissionAction::run($form, ['email' => 'privacy@example.test'], new SubmissionMetaData);
    $held = CreateSubmissionAction::run($form, ['email' => 'privacy@example.test'], new SubmissionMetaData);
    $held->forceFill([
        'legal_hold' => true,
        'payload' => new SubmissionPayloadData(['email' => 'privacy@example.test', 'cv' => ['disk' => 'local', 'path' => 'form-builder/submissions/held-cv.pdf']]),
    ])->save();
    Storage::disk('local')->put('form-builder/submissions/held-cv.pdf', 'claim evidence');

    $subject = persistedPrivacySubject('privacy@example.test');
    $result = resolve(PrivacySubjectRegistry::class)->erase(PrivacySubject::forModel($subject), PrivacyErasureMode::Delete);
    $receipt = $result->receipts[0] ?? null;

    expect($result->failures)->toBe([])
        ->and($receipt?->affectedRecords)->toBe(1)
        ->and($receipt?->retainedRecords)->toBe([PrivacyErasureReceiptData::RETAINED_LEGAL_HOLD => [$held->getKey()]])
        ->and(Submission::query()->whereKey($own->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeTrue();
    Storage::disk('local')->assertExists('form-builder/submissions/held-cv.pdf');
});

it('refuses to delete a form that keeps a submission under legal hold, because its submissions would go with it', function (): void {
    $form = privacyContactForm();
    $held = Submission::factory()->for($form)->create(['legal_hold' => true]);

    expect(fn (): ?bool => $form->delete())->toThrow(FormHasSubmissionsUnderLegalHoldException::class)
        ->and(Form::query()->whereKey($form->getKey())->exists())->toBeTrue()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeTrue();

    $held->forceFill(['legal_hold' => false])->save();

    expect($form->delete())->toBeTrue();
});

it('refuses to permanently delete a site whose forms keep a submission under legal hold, so the cascade cannot destroy it', function (): void {
    $form = privacyContactForm();
    $held = Submission::factory()->for($form)->create(['legal_hold' => true, 'site_id' => $form->site_id]);
    $site = Site::query()->findOrFail($form->site_id);

    expect(fn (): ?bool => $site->forceDelete())->toThrow(SiteHasRetainedRecordsException::class, '1 form submission under legal hold')
        ->and(Site::withTrashed()->whereKey($site->getKey())->exists())->toBeTrue()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeTrue();

    $held->forceFill(['legal_hold' => false])->save();

    expect($site->forceDelete())->toBeTrue()
        ->and(Submission::query()->whereKey($held->getKey())->exists())->toBeFalse();
});

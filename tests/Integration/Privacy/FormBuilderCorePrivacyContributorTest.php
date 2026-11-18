<?php

declare(strict_types=1);

use Capell\Core\Contracts\Privacy\PrivacyReviewResolutions;
use Capell\Core\Data\Privacy\PrivacyReviewCandidateData;
use Capell\Core\Data\Privacy\PrivacySubject;
use Capell\Core\Enums\PrivacyErasureMode;
use Capell\Core\Support\Packages\InstalledRuntimeLifecycle;
use Capell\Core\Support\Privacy\PrivacySubjectRegistry;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Providers\FormBuilderServiceProvider;
use Capell\FormBuilder\Tests\Fixtures\FormBuilderPrivacyTestSubject;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Event::fake([FormSubmitted::class]);
    Mail::fake();

    if (! Schema::hasTable('form_builder_privacy_test_subjects')) {
        Schema::create('form_builder_privacy_test_subjects', static function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }

    // Applications enforce a morph map, so the subject record is mapped as an account model would be.
    Relation::morphMap(['form_builder_privacy_test_subject' => FormBuilderPrivacyTestSubject::class]);
    app()->instance(InstalledRuntimeLifecycle::class, new InstalledRuntimeLifecycle(app()));
    app()->forgetInstance(PrivacySubjectRegistry::class);
    app()->register(FormBuilderServiceProvider::class, force: true);
});

function corePrivacyForm(): Form
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

function storedFormBuilderPrivacySubject(string $email): PrivacySubject
{
    return PrivacySubject::forModel(FormBuilderPrivacyTestSubject::query()->create(['email' => $email]), $email);
}

/**
 * Resolutions a reviewer recorded, keyed by subject email so another subject never sees them.
 *
 * @param  array<int, string>  $resolutions
 */
function fakeFormBuilderReviewResolutions(string $email, array $resolutions): void
{
    app()->instance(PrivacyReviewResolutions::class, new readonly class($email, $resolutions) implements PrivacyReviewResolutions
    {
        /** @param  array<int, string>  $resolutions */
        public function __construct(private string $email, private array $resolutions) {}

        public function for(PrivacySubject $subject, string $contributor, string $candidateType): array
        {
            return $subject->model?->getAttribute('email') === $this->email && $contributor === 'form-builder' && $candidateType === 'form_submission'
                ? $this->resolutions
                : [];
        }
    });
}

it("exports and erases only the subject's own submissions through Core's privacy registry", function (): void {
    $form = corePrivacyForm();
    $own = CreateSubmissionAction::run($form, ['email' => 'privacy@example.test', 'message' => 'Private detail'], new SubmissionMetaData);
    $someoneElses = CreateSubmissionAction::run($form, ['email' => 'other@example.test', 'message' => 'Other detail'], new SubmissionMetaData);
    $mention = CreateSubmissionAction::run($form, ['email' => 'sender@example.test', 'friend_email' => 'privacy@example.test'], new SubmissionMetaData);
    $registry = resolve(PrivacySubjectRegistry::class);
    $subject = storedFormBuilderPrivacySubject('privacy@example.test');

    $export = $registry->export($subject)['form-builder']->data;
    $result = $registry->erase($subject, PrivacyErasureMode::Delete, ['form-builder']);

    expect(array_keys($registry->exporters()))->toContain('form-builder')
        ->and(array_keys($registry->erasers()))->toContain('form-builder')
        ->and(data_get($export, 'submissions.*.payload.values.message'))->toBe(['Private detail'])
        ->and($result->failures)->toBe([])
        ->and($result->receipts)->toHaveCount(1)
        ->and($result->receipts[0]->contributor)->toBe('form-builder')
        ->and($result->receipts[0]->mode)->toBe(PrivacyErasureMode::Delete)
        ->and($result->receipts[0]->affectedRecords)->toBe(1)
        ->and(Submission::query()->whereKey($own->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($someoneElses->getKey())->exists())->toBeTrue()
        ->and(Submission::query()->whereKey($mention->getKey())->exists())->toBeTrue();
});

it('reports a submission that only mentions the subject as a Core review candidate for that subject alone', function (): void {
    $form = corePrivacyForm();
    $mention = CreateSubmissionAction::run($form, ['email' => 'sender@example.test', 'friend_email' => 'privacy@example.test'], new SubmissionMetaData);
    $registry = resolve(PrivacySubjectRegistry::class);

    $candidates = $registry->reviewCandidates(storedFormBuilderPrivacySubject('privacy@example.test'));
    $otherCandidates = $registry->reviewCandidates(storedFormBuilderPrivacySubject('someone@example.test'));

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->contributor)->toBe('form-builder')
        ->and($candidates[0]->candidateType)->toBe('form_submission')
        ->and($candidates[0]->candidateId)->toBe($mention->getKey())
        ->and($candidates[0]->reason)->toBe(PrivacyReviewCandidateData::REASON_EMAIL_MATCH)
        ->and($otherCandidates)->toBe([])
        ->and($registry->honoursRetainedScopes('form-builder'))->toBeFalse();
});

it('exports and erases a mentioned submission once a reviewer links it to that subject only', function (): void {
    $form = corePrivacyForm();
    $mention = CreateSubmissionAction::run($form, ['email' => 'sender@example.test', 'friend_email' => 'privacy@example.test'], new SubmissionMetaData);
    $otherMention = CreateSubmissionAction::run($form, ['email' => 'sender@example.test', 'friend_email' => 'someone@example.test'], new SubmissionMetaData);
    fakeFormBuilderReviewResolutions('privacy@example.test', [$mention->id => 'linked', $otherMention->id => 'unrelated']);
    $registry = resolve(PrivacySubjectRegistry::class);
    $otherSubject = storedFormBuilderPrivacySubject('someone@example.test');

    $otherExport = $registry->export($otherSubject);
    $otherResult = $registry->erase($otherSubject, PrivacyErasureMode::Delete, ['form-builder']);
    $subject = storedFormBuilderPrivacySubject('privacy@example.test');
    $export = $registry->export($subject)['form-builder']->data;
    $result = $registry->erase($subject, PrivacyErasureMode::Delete, ['form-builder']);

    expect($otherExport)->not->toHaveKey('form-builder')
        ->and($otherResult->receipts[0]->affectedRecords)->toBe(0)
        ->and(data_get($export, 'submissions.*.payload.values.friend_email'))->toBe(['privacy@example.test'])
        ->and($result->receipts[0]->affectedRecords)->toBe(1)
        ->and(Submission::query()->whereKey($mention->getKey())->exists())->toBeFalse()
        ->and(Submission::query()->whereKey($otherMention->getKey())->exists())->toBeTrue();
});

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support\Privacy;

use Capell\Core\Contracts\Privacy\PrivacyReviewResolutions;
use Capell\Core\Contracts\Privacy\PrivacySubjectEraser;
use Capell\Core\Contracts\Privacy\PrivacySubjectExporter;
use Capell\Core\Contracts\Privacy\ReviewsPrivacyErasure;
use Capell\Core\Data\Privacy\PrivacyErasureReceiptData;
use Capell\Core\Data\Privacy\PrivacyExportSectionData;
use Capell\Core\Data\Privacy\PrivacyReviewCandidateData;
use Capell\Core\Data\Privacy\PrivacySubject;
use Capell\Core\Enums\PrivacyErasureMode;
use Capell\FormBuilder\Actions\BuildFormSubmissionPrivacyExportAction;
use Capell\FormBuilder\Actions\EraseFormSubmissionPrivacyDataAction;
use Capell\FormBuilder\Actions\ResolveFormSubmissionPrivacyRecordIdsAction;
use Illuminate\Database\Eloquent\Model;
use Override;
use RuntimeException;

/**
 * Exports and erases form submissions through Core's privacy registry.
 *
 * Submissions recorded as the subject's own are erased and exported directly. A submission that
 * only mentions the subject's address becomes an `email_match` review candidate, which holds the
 * request until an operator resolves it: `linked` includes it, anything else leaves it alone.
 */
final readonly class FormBuilderPrivacyContributor implements PrivacySubjectEraser, PrivacySubjectExporter, ReviewsPrivacyErasure
{
    public const string KEY = 'form-builder';

    public const string CANDIDATE_TYPE = 'form_submission';

    public function __construct(private PrivacyReviewResolutions $reviewResolutions) {}

    #[Override]
    public function contributor(): string
    {
        return self::KEY;
    }

    #[Override]
    public function supports(PrivacySubject $subject): bool
    {
        return $subject->model instanceof Model;
    }

    #[Override]
    public function export(PrivacySubject $subject): PrivacyExportSectionData
    {
        return new PrivacyExportSectionData($subject->model instanceof Model
            ? BuildFormSubmissionPrivacyExportAction::run($subject->model, $this->linkedIds($subject))
            : []);
    }

    #[Override]
    public function erase(PrivacySubject $subject, PrivacyErasureMode $mode): PrivacyErasureReceiptData
    {
        // Submissions and their uploads are deleted outright whichever mode is asked for, except those under legal
        // hold, which are kept and named on the receipt.
        if (! $subject->model instanceof Model) {
            return new PrivacyErasureReceiptData(self::KEY, PrivacyErasureMode::Delete, 0);
        }

        $result = EraseFormSubmissionPrivacyDataAction::run($subject->model, $this->linkedIds($subject));

        // A submission kept because its upload could not be deleted still holds the person's data: fail the erasure so
        // the request is not fulfilled. Erasure is safe to repeat, so the request runs again once the file is fixed.
        throw_if($result->failedIds !== [], RuntimeException::class, sprintf(
            'Form Builder could not delete the uploads of %d form submissions; they are kept and the erasure is unfinished.',
            count($result->failedIds),
        ));

        return new PrivacyErasureReceiptData(
            self::KEY,
            PrivacyErasureMode::Delete,
            $result->deleted,
            retainedRecords: $result->legalHoldIds === [] ? [] : [PrivacyErasureReceiptData::RETAINED_LEGAL_HOLD => $result->legalHoldIds],
        );
    }

    #[Override]
    public function reviewCandidates(PrivacySubject $subject): array
    {
        if (! $subject->model instanceof Model) {
            return [];
        }

        $candidates = [];

        foreach (ResolveFormSubmissionPrivacyRecordIdsAction::run($subject->model)->reviewSubmissionIds as $submissionId => $siteId) {
            $candidates[] = new PrivacyReviewCandidateData(self::KEY, self::CANDIDATE_TYPE, $submissionId, PrivacyReviewCandidateData::REASON_EMAIL_MATCH, $siteId);
        }

        return $candidates;
    }

    /** A submission a reviewer marked as retained keeps holding the request until it is resolved another way. */
    #[Override]
    public function honoursRetainedScopes(): bool
    {
        return false;
    }

    #[Override]
    public function retainsSuppressionMarker(): bool
    {
        return false;
    }

    /**
     * Submissions an operator has confirmed belong to the subject.
     *
     * @return list<int>
     */
    private function linkedIds(PrivacySubject $subject): array
    {
        $ids = [];

        foreach ($this->reviewResolutions->for($subject, self::KEY, self::CANDIDATE_TYPE) as $submissionId => $resolution) {
            if ($resolution === 'linked') {
                $ids[] = $submissionId;
            }
        }

        return $ids;
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Spatie\LaravelData\Data;

final class FormSubmissionPrivacyRecordIdsData extends Data
{
    /**
     * @param  list<int>  $submissionIds  submissions recorded as owned by the subject
     * @param  array<int, int|null>  $reviewSubmissionIds  submissions that only mention the subject's address, keyed by id with their site id
     */
    public function __construct(
        public array $submissionIds,
        public array $reviewSubmissionIds = [],
    ) {}
}

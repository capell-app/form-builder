<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Spatie\LaravelData\Data;

final class FormSubmissionPrivacyRecordIdsData extends Data
{
    /**
     * @param  list<int>  $submissionIds
     */
    public function __construct(
        public array $submissionIds,
    ) {}
}

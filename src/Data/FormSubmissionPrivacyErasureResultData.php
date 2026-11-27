<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

/**
 * What a privacy erasure did to a subject's submissions: how many it deleted, which it kept under legal hold, and
 * which it could not delete because an upload could not be removed. A failed one means the erasure is unfinished.
 */
final readonly class FormSubmissionPrivacyErasureResultData
{
    /**
     * @param  list<int>  $legalHoldIds
     * @param  list<int>  $failedIds
     */
    public function __construct(
        public int $deleted,
        public array $legalHoldIds = [],
        public array $failedIds = [],
    ) {}
}

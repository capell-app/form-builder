<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

/** What a privacy erasure did to a subject's submissions: how many it deleted and which it kept under legal hold. */
final readonly class FormSubmissionPrivacyErasureResultData
{
    /** @param  list<int>  $legalHoldIds */
    public function __construct(
        public int $deleted,
        public array $legalHoldIds = [],
    ) {}
}

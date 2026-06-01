<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Spatie\LaravelData\Data;

final class SubmissionSpamScoreData extends Data
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $score = 0,
        public array $reasons = [],
    ) {}

    public function isSpam(int $threshold): bool
    {
        return $this->score >= $threshold;
    }
}

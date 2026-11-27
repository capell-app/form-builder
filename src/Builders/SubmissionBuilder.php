<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Builders;

use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionDeletionGuard;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * @extends Builder<Submission>
 */
final class SubmissionBuilder extends Builder
{
    #[Override]
    public function delete(): mixed
    {
        SubmissionDeletionGuard::assertPermitted();

        return parent::delete();
    }

    #[Override]
    public function forceDelete(): mixed
    {
        SubmissionDeletionGuard::assertPermitted();

        return parent::forceDelete();
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormSubmissionPrivacyRecordIdsData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static FormSubmissionPrivacyRecordIdsData run(Model $subject)
 */
final class ResolveFormSubmissionPrivacyRecordIdsAction
{
    use AsAction;

    public function handle(Model $subject): FormSubmissionPrivacyRecordIdsData
    {
        $email = $this->subjectEmail($subject);

        if ($email === null) {
            return new FormSubmissionPrivacyRecordIdsData([]);
        }

        $submissionIds = [];

        Submission::query()
            ->select(['id', 'payload'])
            ->lazyById(200)
            ->each(function (Submission $submission) use ($email, &$submissionIds): void {
                foreach (Arr::dot($submission->payload->values) as $key => $value) {
                    if (! is_string($value) || ! str_contains(Str::lower((string) $key), 'email')) {
                        continue;
                    }

                    if (Str::lower(trim($value)) === $email) {
                        $submissionKey = $submission->getKey();
                        if (is_numeric($submissionKey)) {
                            $submissionIds[] = (int) $submissionKey;
                        }

                        return;
                    }
                }
            });

        return new FormSubmissionPrivacyRecordIdsData($submissionIds);
    }

    private function subjectEmail(Model $subject): ?string
    {
        if (! array_key_exists('email', $subject->getAttributes())) {
            return null;
        }

        $email = $subject->getAttribute('email');

        return is_string($email) && trim($email) !== '' ? Str::lower(trim($email)) : null;
    }
}

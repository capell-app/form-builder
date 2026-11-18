<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormSubmissionPrivacyRecordIdsData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Finds the subject's form submissions.
 *
 * A submission belongs to the subject only when its recorded owner, taken from the form's visitor
 * email field when it was submitted, is the subject's address. Any other submission whose answers
 * contain the address, under whatever field key ("friend_email", "address", or a field since
 * renamed), may be about someone else: it is reported for review and never erased or exported on
 * that evidence alone. Submissions saved before ownership was recorded are always reviewed.
 *
 * @method static FormSubmissionPrivacyRecordIdsData run(Model $subject)
 */
final class ResolveFormSubmissionPrivacyRecordIdsAction
{
    use AsFake;
    use AsObject;

    public function handle(Model $subject): FormSubmissionPrivacyRecordIdsData
    {
        $email = $this->subjectEmail($subject);

        if ($email === null) {
            return new FormSubmissionPrivacyRecordIdsData([]);
        }

        $hash = Submission::subjectEmailHash($email);
        $ownedIds = [];
        $reviewIds = [];

        Submission::query()
            ->select(['id', 'site_id', 'payload', 'subject_email_hash'])
            ->lazyById(200)
            ->each(function (Submission $submission) use ($email, $hash, &$ownedIds, &$reviewIds): void {
                $id = $submission->getKey();

                if (! is_numeric($id)) {
                    return;
                }

                if ($submission->subject_email_hash === $hash) {
                    $ownedIds[] = (int) $id;

                    return;
                }

                if ($this->mentions($submission, $email)) {
                    $reviewIds[(int) $id] = is_numeric($submission->site_id) ? (int) $submission->site_id : null;
                }
            });

        return new FormSubmissionPrivacyRecordIdsData($ownedIds, $reviewIds);
    }

    private function mentions(Submission $submission, string $email): bool
    {
        foreach (Arr::dot($submission->payload->values ?? []) as $value) {
            if (is_string($value) && str_contains(Str::lower($value), $email)) {
                return true;
            }
        }

        return false;
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

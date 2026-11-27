<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionDeletionGuard;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * The one routine that deletes a submission: an operator's delete, a privacy erasure, the sweep that finishes
 * erasures a hold postponed, and retention pruning all come through here. No other code deletes submission rows.
 *
 * It works on the locked, current row. A row under legal hold is never deleted. Its uploads are deleted first, and
 * the row goes only when every upload did. If any upload could not be deleted or its stored reference cannot be
 * resolved, the row and its pointers to the files
 * are kept and marked as awaiting erasure, so the next prune retries it.
 *
 * Deleting a submission that was awaiting erasure (kept under a hold, or kept because a file could not be deleted)
 * announces it, whichever route deleted it, so the privacy request that kept it records that its erasure finished.
 * The announcement is a plain string event, so Form Builder needs no Privacy Center class and boots without it.
 *
 * @method static bool run(Submission|int $submission)
 */
final class DeleteSubmissionAction
{
    use AsFake;
    use AsObject;

    /** Payload: contributor key, retention reason, erased record ids. */
    public const string RETAINED_RECORDS_ERASED_EVENT = 'capell.privacy.retained-records-erased';

    /** Returns true only when the submission row and all of its uploads were deleted. */
    public function handle(Submission|int $submission): bool
    {
        $id = $submission instanceof Submission ? $submission->getKey() : $submission;

        $outcome = DB::transaction(static function () use ($id): ?bool {
            $current = Submission::query()->whereKey($id)->lockForUpdate()->first();

            if (! $current instanceof Submission || $current->legal_hold) {
                return null;
            }

            // A payload that cannot be decrypted (for example after an app key change) reads as empty, which would delete
            // the row while its files and their pointers are lost. Keep it and count it as failed instead.
            $failed = self::payloadIsReadable($current)
                ? DeleteSubmissionUploadsAction::make()->deleteFilesOf($current)['failed']
                : 1;

            if ($failed > 0) {
                if ($current->erasure_requested_at === null) {
                    $current->forceFill(['erasure_requested_at' => now()])->save();
                }

                report(new RuntimeException(sprintf('Could not delete %d uploaded files of form submission %d, or could not decrypt its payload; it is kept and will be retried.', $failed, $current->id)));

                return null;
            }

            $awaitingErasure = $current->erasure_requested_at !== null;

            return SubmissionDeletionGuard::permit(static fn (): ?bool => $current->delete()) ? $awaitingErasure : null;
        });

        if ($outcome === null) {
            return false;
        }

        if ($outcome && is_numeric($id)) {
            event(self::RETAINED_RECORDS_ERASED_EVENT, ['form-builder', 'legal_hold', [(int) $id]]);
        }

        return true;
    }

    /** Whether the stored payload decrypts, or is a legacy unencrypted JSON object. Empty means there is nothing to read. */
    private static function payloadIsReadable(Submission $submission): bool
    {
        $raw = $submission->getRawOriginal('payload');

        if ($raw === null || $raw === '') {
            return true;
        }

        if (! is_string($raw)) {
            return false;
        }

        $decoded = json_decode($raw, true);
        $stored = is_string($decoded) ? $decoded : $raw;

        try {
            Crypt::decryptString($stored);

            return true;
        } catch (DecryptException) {
            return is_array(json_decode($stored, true));
        }
    }
}

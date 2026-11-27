<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * @method static int run(Builder<Submission> $submissions)
 */
final class DeleteSubmissionUploadsAction
{
    use AsFake;
    use AsObject;

    /**
     * Removes the stored files referenced by the given submissions' payloads and returns how many were deleted. Only
     * files under the configured upload directory are touched, whatever the payload says. Each submission is decided on
     * its locked, current row: one under legal hold keeps its files.
     *
     * @param  Builder<Submission>  $submissions
     */
    public function handle(Builder $submissions): int
    {
        $deleted = 0;

        foreach ((clone $submissions)->select('id')->pluck('id') as $id) {
            $deleted += DB::transaction(function () use ($id): int {
                $current = Submission::query()->whereKey($id)->lockForUpdate()->first();

                return $current instanceof Submission && ! $current->legal_hold ? $this->deleteFilesOf($current)['deleted'] : 0;
            });
        }

        return $deleted;
    }

    /**
     * @internal for DeleteSubmissionAction and handle(), which pass the row they locked and checked for a hold.
     *
     * Removes one submission's stored files. A file the disk could not delete, or a stored reference that cannot be resolved
     * (incomplete, outside the upload directory, or on a disk that is not configured), counts as failed, so a caller can keep
     * the submission (and its pointer to the file) and try again later rather than lose track of the file.
     *
     * @return array{deleted: int, failed: int}
     */
    public function deleteFilesOf(Submission $submission): array
    {
        // Never the files of a held submission, whoever calls.
        throw_if($submission->legal_hold, LogicException::class, 'The files of a submission under legal hold are never deleted.');

        $deleted = 0;
        $failed = 0;
        $directory = $this->uploadDirectory();

        foreach ($submission->payload->values ?? [] as $field => $value) {
            // A field kept without its file (uploads not stored) has no disk or path and nothing to delete.
            if (! is_array($value) || (! array_key_exists('disk', $value) && ! array_key_exists('path', $value))) {
                continue;
            }

            $reason = match (true) {
                ! is_string($value['disk'] ?? null) || ! is_string($value['path'] ?? null) => 'the stored file reference is incomplete',
                // Never delete outside the upload directory, whatever the payload says; keep the row instead.
                ! str_starts_with($value['path'], $directory . '/') || str_contains($value['path'], '..') => 'the stored file is outside the upload directory',
                ! is_array(config('filesystems.disks.' . $value['disk'])) => 'the stored file is on a disk that is not configured',
                ! self::deleteFile($value['disk'], $value['path']) => 'the disk did not delete the stored file',
                default => null,
            };

            if ($reason === null) {
                $deleted++;

                continue;
            }

            $failed++;
            Log::warning('A form submission upload could not be deleted, so the submission is kept until it can be.', [
                'submission_id' => $submission->getKey(),
                'field' => $field,
                'reason' => $reason,
            ]);
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /** A disk that throws counts as a failed delete, so one bad file never undoes the files already deleted. */
    private static function deleteFile(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function uploadDirectory(): string
    {
        $directory = config('capell-form-builder.uploads.directory', 'form-builder/submissions');

        return is_string($directory) && trim($directory, '/') !== '' ? trim($directory, '/') : 'form-builder/submissions';
    }
}

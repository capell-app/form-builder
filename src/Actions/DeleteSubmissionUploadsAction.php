<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Builder<Submission> $submissions)
 */
final class DeleteSubmissionUploadsAction
{
    use AsFake;
    use AsObject;

    /**
     * Removes the stored files referenced by the given submissions' payloads and returns how many were deleted. Only
     * files under the configured upload directory are touched, whatever the payload says.
     *
     * @param  Builder<Submission>  $submissions
     */
    public function handle(Builder $submissions): int
    {
        $deleted = 0;
        $directory = $this->uploadDirectory();

        foreach ($submissions->lazyById() as $submission) {
            foreach ($submission->payload->values ?? [] as $value) {
                if (! is_array($value) || ! is_string($value['disk'] ?? null) || ! is_string($value['path'] ?? null)) {
                    continue;
                }

                $path = $value['path'];

                if (! str_starts_with($path, $directory . '/') || str_contains($path, '..')) {
                    continue;
                }

                if (! is_array(config('filesystems.disks.' . $value['disk']))) {
                    continue;
                }

                if (Storage::disk($value['disk'])->delete($path)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function uploadDirectory(): string
    {
        $directory = config('capell-form-builder.uploads.directory', 'form-builder/submissions');

        return is_string($directory) && trim($directory, '/') !== '' ? trim($directory, '/') : 'form-builder/submissions';
    }
}

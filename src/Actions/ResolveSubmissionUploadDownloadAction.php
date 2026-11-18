<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method static StreamedResponse run(Submission $submission, string $payloadKey)
 */
final class ResolveSubmissionUploadDownloadAction
{
    use AsFake;
    use AsObject;

    /**
     * Streams one stored upload as an attachment. The caller authorises the submission; the storage path is read from
     * the stored payload and confined to the configured upload directory, never taken from the request.
     */
    public function handle(Submission $submission, string $payloadKey): StreamedResponse
    {
        $value = $submission->payload->values[$payloadKey] ?? null;

        abort_unless(is_array($value) && is_string($value['disk'] ?? null) && is_string($value['path'] ?? null), 404);

        $directory = config('capell-form-builder.uploads.directory', 'form-builder/submissions');
        $directory = is_string($directory) && trim($directory, '/') !== '' ? trim($directory, '/') : 'form-builder/submissions';
        $path = $value['path'];

        abort_unless(str_starts_with($path, $directory . '/') && ! str_contains($path, '..'), 404);
        abort_unless(is_array(config('filesystems.disks.' . $value['disk'])), 404);

        $disk = Storage::disk($value['disk']);

        abort_unless($disk->exists($path), 404);

        $name = is_string($value['original_name'] ?? null) ? $value['original_name'] : basename($path);

        return $disk->download($path, preg_replace('/[\x00-\x1f\x7f"\\\\\/]/', '_', $name) ?? 'download', ['X-Content-Type-Options' => 'nosniff']);
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

final class BuildSubmissionPayloadDataAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(Form $form, array $validated, bool $storeUploads = true): SubmissionPayloadData
    {
        $values = [];

        foreach (ResolveVisibleFormFieldsAction::run($form, $validated) as $field) {
            if (! $field->type->isStoredInPayload()) {
                continue;
            }

            if (array_key_exists($field->key, $validated)) {
                $values[$field->key] = $this->storedValue($validated[$field->key], $storeUploads);
            }
        }

        return new SubmissionPayloadData($values);
    }

    private function storedValue(mixed $value, bool $storeUploads): mixed
    {
        if (! $value instanceof UploadedFile) {
            return $value;
        }

        if (! $storeUploads) {
            return [
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getMimeType(),
                'size' => $value->getSize(),
            ];
        }

        $disk = $this->uploadDisk();
        $path = $value->store($this->uploadDirectory(), ['disk' => $disk]);
        $storage = Storage::disk($disk);

        return [
            'original_name' => $value->getClientOriginalName(),
            'mime_type' => $storage->mimeType($path) ?: $value->getMimeType(),
            'size' => $storage->size($path),
            'disk' => $disk,
            'path' => $path,
        ];
    }

    private function uploadDisk(): string
    {
        $disk = config('capell-form-builder.uploads.disk', 'local');

        return is_string($disk) && trim($disk) !== '' ? $disk : 'local';
    }

    private function uploadDirectory(): string
    {
        $directory = config('capell-form-builder.uploads.directory', 'form-builder/submissions');

        if (! is_string($directory) || trim($directory) === '') {
            return 'form-builder/submissions';
        }

        return trim($directory, '/');
    }
}

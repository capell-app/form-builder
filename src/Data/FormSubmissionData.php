<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class FormSubmissionData extends Data
{
    public function __construct(
        public int|string|null $formId,
        public int|string|null $siteId,
        public ?string $formHandle,
        public int|string|null $submissionId,
        public bool $stored,
        public SubmissionStatus $status,
        public SubmissionPayloadData $payload,
        public SubmissionMetaData $metadata,
    ) {}

    public static function fromStored(Form $form, Submission $submission): self
    {
        return new self(
            formId: self::modelKey($form),
            siteId: $submission->site_id ?? $form->site_id,
            formHandle: is_string($form->handle ?? null) ? $form->handle : null,
            submissionId: self::modelKey($submission),
            stored: true,
            status: $submission->status instanceof SubmissionStatus ? $submission->status : SubmissionStatus::New,
            payload: $submission->payload ?? new SubmissionPayloadData,
            metadata: $submission->meta ?? new SubmissionMetaData,
        );
    }

    public static function fromUnstored(
        Form $form,
        SubmissionPayloadData $payload,
        SubmissionMetaData $metadata,
        SubmissionStatus $status = SubmissionStatus::New,
    ): self {
        return new self(
            formId: self::modelKey($form),
            siteId: $form->site_id,
            formHandle: is_string($form->handle ?? null) ? $form->handle : null,
            submissionId: null,
            stored: false,
            status: $status,
            payload: $payload,
            metadata: $metadata,
        );
    }

    private static function modelKey(Model $model): int|string|null
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}

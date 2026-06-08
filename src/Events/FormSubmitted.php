<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Events;

use Capell\FormBuilder\Data\FormSubmissionData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class FormSubmitted
{
    use Dispatchable;

    public FormSubmissionData $submissionData;

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public Form $form,
        public ?Submission $submission = null,
        public ?SubmissionMetaData $metadata = null,
        public ?array $payload = null,
        ?FormSubmissionData $submissionData = null,
    ) {
        if (! $this->metadata instanceof SubmissionMetaData) {
            $this->metadata = $submissionData instanceof FormSubmissionData
                ? $submissionData->metadata
                : $this->submissionMetadata($submission);
        }

        if ($this->payload === null) {
            $this->payload = $submissionData instanceof FormSubmissionData
                ? $submissionData->payload->values
                : ($submission instanceof Submission ? $submission->payload->values : []);
        }

        $this->submissionData = $submissionData ?? $this->submissionData($form, $submission, $this->metadata, $this->payload);
    }

    private function submissionMetadata(?Submission $submission): SubmissionMetaData
    {
        if (! $submission instanceof Submission) {
            return new SubmissionMetaData;
        }

        return $submission->meta instanceof SubmissionMetaData
            ? $submission->meta
            : new SubmissionMetaData;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submissionData(
        Form $form,
        ?Submission $submission,
        SubmissionMetaData $metadata,
        array $payload,
    ): FormSubmissionData {
        if ($submission instanceof Submission) {
            return new FormSubmissionData(
                formId: $this->modelKey($form),
                siteId: $submission->site_id ?? $form->site_id,
                formHandle: is_string($form->handle ?? null) ? $form->handle : null,
                submissionId: $this->modelKey($submission),
                stored: true,
                status: $submission->status instanceof SubmissionStatus ? $submission->status : SubmissionStatus::New,
                payload: new SubmissionPayloadData($payload),
                metadata: $metadata,
            );
        }

        return FormSubmissionData::fromUnstored(
            form: $form,
            payload: new SubmissionPayloadData($payload),
            metadata: $metadata,
        );
    }

    private function modelKey(Model $model): int|string|null
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}

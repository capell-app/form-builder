<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Events;

use Capell\FormBuilder\Data\FormSubmissionData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
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
        $this->metadata ??= $submissionData?->metadata ?? $submission?->meta ?? new SubmissionMetaData;
        $this->payload ??= $submissionData?->payload->values ?? $submission?->payload?->values ?? [];
        $this->submissionData = $submissionData ?? $this->submissionData($form, $submission, $this->metadata, $this->payload);
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
                formId: $form->getKey(),
                siteId: $submission->site_id ?? $form->site_id,
                formHandle: is_string($form->handle ?? null) ? $form->handle : null,
                submissionId: $submission->getKey(),
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
}

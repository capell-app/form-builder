<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Tests\Fixtures;

use Capell\Core\Events\Capabilities\FormSubmissionReceived;
use Capell\FormBuilder\Actions\DispatchCoreFormSubmissionEventAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;

/** Builds the Core event Form Builder dispatches, with the same defaults as a real submission. */
final class CoreFormSubmission
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function make(
        Form $form,
        ?Submission $submission = null,
        ?SubmissionMetaData $metadata = null,
        ?array $payload = null,
    ): FormSubmissionReceived {
        $metadata ??= $submission?->meta instanceof SubmissionMetaData ? $submission->meta : new SubmissionMetaData;
        $payload ??= $submission instanceof Submission ? $submission->payload->values : [];

        return DispatchCoreFormSubmissionEventAction::make()->build($form, $payload, $metadata, $submission);
    }
}

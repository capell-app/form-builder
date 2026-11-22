<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Data\Capabilities\FormReferenceData;
use Capell\Core\Data\Capabilities\FormSubmissionSourceData;
use Capell\Core\Data\Capabilities\SubmittedFormData;
use Capell\Core\Events\Capabilities\FormSubmissionReceived;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Announces an accepted submission through Core's FormSubmissionReceived event,
 * so other packages react to form submissions without naming Form Builder classes.
 *
 * @method static void run(Form $form, array<string, mixed> $values, SubmissionMetaData $meta, ?Submission $submission = null)
 */
final class DispatchCoreFormSubmissionEventAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Form $form, array $values, SubmissionMetaData $meta, ?Submission $submission = null): void
    {
        event($this->build($form, $values, $meta, $submission));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function build(Form $form, array $values, SubmissionMetaData $meta, ?Submission $submission = null): FormSubmissionReceived
    {
        $submissionId = $submission?->getKey();
        $formId = $form->getKey();
        $siteId = $form->getAttribute('site_id');
        $handle = $form->getAttribute('handle');
        $name = $form->getAttribute('name');

        return new FormSubmissionReceived(new SubmittedFormData(
            form: new FormReferenceData(
                id: is_numeric($formId) ? (int) $formId : 0,
                key: is_string($handle) ? $handle : '',
                name: is_string($name) ? $name : '',
                siteId: is_numeric($siteId) ? (int) $siteId : null,
            ),
            submissionId: is_int($submissionId) ? $submissionId : null,
            values: $values,
            submittedAt: CarbonImmutable::now(),
            locale: app()->getLocale(),
            sourceUrl: $meta->url,
            // Only a stored submission has a source: listeners that keep a link to it ignore unstored ones.
            source: $submission instanceof Submission ? FormSubmissionSourceData::forModel($submission) : null,
            referer: $meta->referer,
            userAgent: $meta->userAgent,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSubmissionData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final class DispatchUnstoredFormSubmissionAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Form $form, array $input, SubmissionMetaData $meta): FormSubmissionData
    {
        $input = CalculateFormFieldValuesAction::run($form, $input);
        $spamScore = CalculateSubmissionSpamScoreAction::run($form, $input, $meta);
        $meta = new SubmissionMetaData(
            ipAddress: $meta->ipAddress,
            userAgent: $meta->userAgent,
            url: $meta->url,
            referer: $meta->referer,
            spamScore: $spamScore->score,
            spamReasons: $spamScore->reasons,
        );

        if ($spamScore->isSpam($this->spamThreshold()) && $this->hasTriggeredHoneypot($form, $input)) {
            return FormSubmissionData::fromUnstored($form, new SubmissionPayloadData, $meta, SubmissionStatus::Spam);
        }

        $validated = Validator::make($input, BuildFormValidationRulesAction::run($form, $input))->validate();
        $payload = BuildSubmissionPayloadDataAction::run($form, $validated, storeUploads: false);

        if ($spamScore->isSpam($this->spamThreshold())) {
            return FormSubmissionData::fromUnstored($form, $payload, $meta, SubmissionStatus::Spam);
        }

        $submissionData = FormSubmissionData::fromUnstored($form, $payload, $meta);

        event(new FormSubmitted($form, metadata: $meta, payload: $payload->values, submissionData: $submissionData));

        return $submissionData;
    }

    private function spamThreshold(): int
    {
        $threshold = config('capell-form-builder.spam_scoring.spam_threshold', 75);

        return is_numeric($threshold) ? max(1, min(100, (int) $threshold)) : 75;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasTriggeredHoneypot(Form $form, array $input): bool
    {
        foreach ($form->schema ?? [] as $field) {
            if (! $field instanceof FormFieldData) {
                $field = FormFieldData::from($field);
            }

            if (! $field->type->isSpamTrap()) {
                continue;
            }

            if (filled($input[$field->key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}

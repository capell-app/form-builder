<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Submission run(Form $form, array<string, mixed> $input, SubmissionMetaData $meta)
 */
class CreateSubmissionAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Form $form, array $input, SubmissionMetaData $meta): Submission
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
            return $this->createSubmission($form, [], $meta, SubmissionStatus::Spam);
        }

        $validated = Validator::make($input, BuildFormValidationRulesAction::run($form, $input))->validate();
        $payload = BuildSubmissionPayloadDataAction::run($form, $validated);

        if ($spamScore->isSpam($this->spamThreshold())) {
            return $this->createSubmission($form, $payload->values, $meta, SubmissionStatus::Spam);
        }

        $submission = $this->createSubmission($form, $payload->values, $meta, SubmissionStatus::New);

        event(new FormSubmitted($form, $submission, metadata: $submission->meta, payload: $payload->values));
        SendSubmissionNotificationAction::run($submission);
        SendSubmissionAutoresponderAction::run($submission);
        DispatchSubmissionWebhookAction::run($submission);

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createSubmission(
        Form $form,
        array $payload,
        SubmissionMetaData $meta,
        SubmissionStatus $status,
    ): Submission {
        $submission = new Submission;
        $submission->forceFill([
            'form_id' => $form->getKey(),
            'site_id' => $form->site_id,
            'payload' => new SubmissionPayloadData($payload),
            'meta' => $meta,
            'status' => $status,
            'submitted_at' => now(),
        ])->save();

        return $submission;
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

    private function spamThreshold(): int
    {
        $threshold = config('capell-form-builder.spam_scoring.spam_threshold', 75);

        return is_numeric($threshold) ? max(1, min(100, (int) $threshold)) : 75;
    }
}

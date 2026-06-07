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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSubmissionAction
{
    use AsAction;

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

        if ($spamScore->isSpam($this->spamThreshold())) {
            return $this->createSubmission($form, $this->storedPayload($form, $validated), $meta, SubmissionStatus::Spam);
        }

        $submission = $this->createSubmission($form, $this->storedPayload($form, $validated), $meta, SubmissionStatus::New);

        event(new FormSubmitted($form, $submission));
        SendSubmissionNotificationAction::run($submission);
        SendSubmissionAutoresponderAction::run($submission);

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
            if (is_array($field)) {
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function storedPayload(Form $form, array $validated): array
    {
        $values = [];

        foreach (ResolveVisibleFormFieldsAction::run($form, $validated) as $field) {
            if (! $field->type->isStoredInPayload()) {
                continue;
            }

            if (array_key_exists($field->key, $validated)) {
                $values[$field->key] = $this->storedValue($validated[$field->key]);
            }
        }

        return $values;
    }

    private function storedValue(mixed $value): mixed
    {
        if (! $value instanceof UploadedFile) {
            return $value;
        }

        return [
            'original_name' => $value->getClientOriginalName(),
            'mime_type' => $value->getClientMimeType(),
            'size' => $value->getSize(),
        ];
    }
}

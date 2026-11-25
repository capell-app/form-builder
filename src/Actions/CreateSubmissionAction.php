<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Jobs\DispatchSubmissionWebhookJob;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Throwable;

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

        $validated = Validator::make(
            $input,
            BuildFormValidationRulesAction::run($form, $input),
            [],
            BuildFormValidationAttributesAction::run($form, $input),
        )->validate();
        if ($spamScore->isSpam($this->spamThreshold())) {
            return $this->createSubmission($form, $validated, $meta, SubmissionStatus::Spam);
        }

        $submission = $this->createSubmission($form, $validated, $meta, SubmissionStatus::New);
        $submissionId = $submission->getKey();
        throw_unless(is_int($submissionId), RuntimeException::class, 'Form submission must have an integer key.');
        $values = $submission->payload->values;

        event(new FormSubmitted($form, $submission, metadata: $submission->meta, payload: $values));
        DispatchCoreFormSubmissionEventAction::run($form, $values, $meta, $submission);
        DeliverSubmissionMailAction::run($submission);
        dispatch(new DispatchSubmissionWebhookJob($submissionId))->afterCommit();

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $validated  the validated input, or an empty array for a honeypot rejection
     */
    private function createSubmission(
        Form $form,
        array $validated,
        SubmissionMetaData $meta,
        SubmissionStatus $status,
    ): Submission {
        $submission = new Submission;
        $submission->forceFill([
            'form_id' => $form->getKey(),
            'site_id' => $form->site_id,
            'meta' => $meta,
            'status' => $status,
            'submitted_at' => now(),
        ]);
        $submission->setRelation('form', $form);

        $accepted = $status === SubmissionStatus::New;
        if ($accepted) {
            $this->selectPaymentRail($submission, $form, $validated);
        }

        $submission->forceFill([
            'payload' => $validated === []
                ? new SubmissionPayloadData([])
                : BuildSubmissionPayloadDataAction::run($form, $validated, storeUploads: $accepted),
        ]);

        if ($accepted) {
            // Ownership is recorded from the form's visitor email field at submission time, so a
            // later schema change or a field such as "friend_email" never decides whose data this is.
            $subjectEmail = ResolveSubmissionReplyAddressAction::run($submission);
            $submission->forceFill([
                'subject_email_hash' => $subjectEmail === null ? null : Submission::subjectEmailHash($subjectEmail),
                // The delivery intent is saved with the submission, so a queue failure is recoverable.
                'delivery_pending_at' => now(),
            ]);
        }

        try {
            $submission->save();
        } catch (Throwable $throwable) {
            // The files were written while the payload was built, so a row that never saved would leave them
            // stored with nothing pointing at them.
            $this->discardStoredUploads($submission->payload->values);

            throw $throwable;
        }

        return $submission;
    }

    /** @param array<string, mixed> $values */
    private function discardStoredUploads(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value) && is_string($value['disk'] ?? null) && is_string($value['path'] ?? null)) {
                Storage::disk($value['disk'])->delete($value['path']);
            }
        }
    }

    /** @param array<string, mixed> $validated */
    private function selectPaymentRail(Submission $submission, Form $form, array $validated): void
    {
        if (! $this->hasPaymentField($form, $validated)) {
            return;
        }

        $rail = ResolveFormPaymentCheckoutRailAction::run();
        if ($rail === null) {
            return;
        }

        // The optional rail may inspect the accepted fields, but has no need to write their files.
        $submission->forceFill([
            'payload' => BuildSubmissionPayloadDataAction::run($form, $validated, storeUploads: false),
        ]);
        if (! $rail->selects($submission)) {
            return;
        }

        if (! Route::has('capell-payments.form-builder.selected-checkout')) {
            throw ValidationException::withMessages([
                'payment' => __('capell-form-builder::generic.payment_checkout_unavailable'),
            ]);
        }

        $submission->forceFill(['payment_rail' => $rail->key()]);
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

    /** @param array<string, mixed> $payload */
    private function hasPaymentField(Form $form, array $payload): bool
    {
        return ResolveVisibleFormFieldsAction::run($form, $payload)
            ->contains(static fn (FormFieldData $field): bool => $field->type === FormFieldType::Payment
                && (isset($payload[$field->key]) || ($field->paymentAmountCents ?? 0) > 0));
    }

    private function spamThreshold(): int
    {
        $threshold = config('capell-form-builder.spam_scoring.spam_threshold', 75);

        return is_numeric($threshold) ? max(1, min(100, (int) $threshold)) : 75;
    }
}

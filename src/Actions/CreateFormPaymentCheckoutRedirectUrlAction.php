<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Bridges\Payments\IsFormPaymentIntegrationAvailableAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class CreateFormPaymentCheckoutRedirectUrlAction
{
    use AsFake;
    use AsObject;

    public function handle(Submission $submission): ?string
    {
        if ($submission->paymentRail() !== null) {
            if (! Route::has('capell-payments.form-builder.selected-checkout')) {
                throw ValidationException::withMessages([
                    'payment' => __('capell-form-builder::generic.payment_checkout_unavailable'),
                ]);
            }

            return CreateFormPaymentCheckoutUrlAction::run($submission);
        }

        if (! IsFormPaymentIntegrationAvailableAction::run()) {
            return null;
        }

        // A spam-scored submission is kept for review but never handed a checkout link.
        if ($submission->status === SubmissionStatus::Spam) {
            return null;
        }

        if (! $this->hasPaymentField($submission)) {
            return null;
        }

        if (! Route::has('capell-payments.form-builder.checkout')) {
            return null;
        }

        $url = CreateFormPaymentCheckoutUrlAction::run($submission);

        return $url !== '' ? $url : null;
    }

    private function hasPaymentField(Submission $submission): bool
    {
        $submission->loadMissing('form');
        $form = $submission->form;

        if (! $form instanceof Form) {
            return false;
        }

        // A payment field the visitor never saw, because its conditions were not met, must not charge them.
        return ResolveVisibleFormFieldsAction::run($form, $submission->payload->values ?? [])
            ->contains(static fn (FormFieldData $field): bool => $field->type === FormFieldType::Payment);
    }
}

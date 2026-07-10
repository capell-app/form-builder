<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Actions\CreateCheckoutSessionAction;
use Capell\Payments\Data\CheckoutLineItemData;
use Capell\Payments\Data\CreateCheckoutSessionData;
use Capell\Payments\Enums\PaymentPurpose;
use Capell\Payments\Models\CheckoutSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static CheckoutSession run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null)
 */
final class CreateFormPaymentCheckoutSessionAction
{
    use AsAction;

    public function handle(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): CheckoutSession
    {
        $paymentData = ResolveFormPaymentCheckoutDataAction::run($submission, $successUrl, $cancelUrl);

        return CreateCheckoutSessionAction::run(new CreateCheckoutSessionData(
            successUrl: $paymentData->successUrl,
            cancelUrl: $paymentData->cancelUrl,
            lineItems: [
                new CheckoutLineItemData(
                    name: $paymentData->field->label,
                    amount: $paymentData->amountCents,
                    currency: $paymentData->currency,
                    metadata: [
                        'form_id' => (string) $paymentData->form->getKey(),
                        'submission_id' => (string) $paymentData->submission->getKey(),
                        'field_key' => $paymentData->field->key,
                    ],
                ),
            ],
            purpose: PaymentPurpose::FormPayment,
            siteId: $paymentData->form->site_id,
            customerEmail: $paymentData->customerEmail,
            payableType: 'form_builder.submission',
            payableId: (string) $paymentData->submission->getKey(),
            sourceType: 'form_builder.form',
            sourceId: (string) $paymentData->form->getKey(),
            referenceId: 'form-submission-' . $paymentData->submission->getKey(),
            idempotencyKey: 'form-payment-' . $paymentData->submission->getKey() . '-' . $paymentData->field->key,
            metadata: [
                'form_id' => (string) $paymentData->form->getKey(),
                'submission_id' => (string) $paymentData->submission->getKey(),
                'field_key' => $paymentData->field->key,
            ],
        ));
    }
}

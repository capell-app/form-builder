<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Actions\CreateCheckoutSessionAction;
use Capell\Payments\Data\CheckoutLineItemData;
use Capell\Payments\Data\CreateCheckoutSessionData;
use Capell\Payments\Enums\PaymentPurpose;
use Capell\Payments\Models\CheckoutSession;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

/**
 * @method static CheckoutSession run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null)
 */
final class CreateFormPaymentCheckoutSessionAction
{
    use AsAction;

    public function handle(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): CheckoutSession
    {
        if (! IsFormPaymentIntegrationAvailableAction::run()) {
            throw new LogicException('The Payments integration is not available.');
        }

        $paymentData = ResolveFormPaymentCheckoutDataAction::run($submission, $successUrl, $cancelUrl);
        $formKey = $this->modelKey($paymentData->form->getKey(), 'form');
        $submissionKey = $this->modelKey($paymentData->submission->getKey(), 'submission');

        return CreateCheckoutSessionAction::run(new CreateCheckoutSessionData(
            successUrl: $paymentData->successUrl,
            cancelUrl: $paymentData->cancelUrl,
            lineItems: [
                new CheckoutLineItemData(
                    name: $paymentData->field->label,
                    amount: $paymentData->amountCents,
                    currency: $paymentData->currency,
                    metadata: [
                        'form_id' => $formKey,
                        'submission_id' => $submissionKey,
                        'field_key' => $paymentData->field->key,
                    ],
                ),
            ],
            purpose: PaymentPurpose::FormPayment,
            siteId: $paymentData->form->site_id,
            customerEmail: $paymentData->customerEmail,
            payableType: 'form_builder.submission',
            payableId: $submissionKey,
            sourceType: 'form_builder.form',
            sourceId: $formKey,
            referenceId: 'form-submission-' . $submissionKey,
            idempotencyKey: 'form-payment-' . $submissionKey . '-' . $paymentData->field->key,
            metadata: [
                'form_id' => $formKey,
                'submission_id' => $submissionKey,
                'field_key' => $paymentData->field->key,
            ],
        ));
    }

    private function modelKey(mixed $key, string $model): string
    {
        if (! is_int($key) && ! is_string($key)) {
            throw new UnexpectedValueException(sprintf('The %s key must be an integer or string.', $model));
        }

        return (string) $key;
    }
}

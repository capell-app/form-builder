<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormPaymentCheckoutData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Actions\ValidateFormPaymentReturnUrlAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use UnexpectedValueException;

/**
 * @method static FormPaymentCheckoutData run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null)
 */
final class ResolveFormPaymentCheckoutDataAction
{
    use AsFake;
    use AsObject;

    public function handle(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): FormPaymentCheckoutData
    {
        if (! IsFormPaymentIntegrationAvailableAction::run()) {
            throw new LogicException('The Payments integration is not available.');
        }

        $submission->loadMissing('form');

        /** @var Form $form */
        $form = $submission->form;
        $field = $this->paymentField($form);
        $amount = $this->amount($field, $submission);
        $currency = $this->currency($field);
        $successUrl = ValidateFormPaymentReturnUrlAction::run($successUrl);
        $cancelUrl = ValidateFormPaymentReturnUrlAction::run($cancelUrl);
        $successUrl = is_string($successUrl) ? $successUrl : null;
        $cancelUrl = is_string($cancelUrl) ? $cancelUrl : null;

        return new FormPaymentCheckoutData(
            form: $form,
            submission: $submission,
            field: $field,
            amountCents: $amount,
            currency: $currency,
            successUrl: $successUrl ?? $this->defaultSuccessUrl($submission),
            cancelUrl: $cancelUrl ?? $this->defaultCancelUrl($submission),
            customerEmail: $this->email($form, $submission),
        );
    }

    private function paymentField(Form $form): FormFieldData
    {
        foreach ($form->schema ?? [] as $field) {
            $field = $field instanceof FormFieldData ? $field : FormFieldData::from($field);

            if ($field->type === FormFieldType::Payment) {
                return $field;
            }
        }

        throw ValidationException::withMessages([
            'payment' => __('capell-payments::generic.form_payments.no_payment_field'),
        ]);
    }

    private function amount(FormFieldData $field, Submission $submission): int
    {
        if (is_int($field->paymentAmountCents) && $field->paymentAmountCents > 0) {
            return $field->paymentAmountCents;
        }

        $storedAmount = $this->payloadValue($submission, $field->key);

        if (is_numeric($storedAmount) && (int) $storedAmount > 0) {
            return (int) $storedAmount;
        }

        throw ValidationException::withMessages([
            $field->key => __('capell-payments::generic.form_payments.invalid_amount'),
        ]);
    }

    private function currency(FormFieldData $field): string
    {
        $configuredCurrency = config('capell-payments.form_builder.default_currency', 'gbp');
        $currency = strtolower($field->paymentCurrency ?: (is_string($configuredCurrency) ? $configuredCurrency : 'gbp'));

        return preg_match('/^[a-z]{3}$/', $currency) === 1 ? $currency : 'gbp';
    }

    private function email(Form $form, Submission $submission): ?string
    {
        foreach ($form->schema ?? [] as $field) {
            $field = $field instanceof FormFieldData ? $field : FormFieldData::from($field);

            if ($field->type !== FormFieldType::Email) {
                continue;
            }

            $value = $this->payloadValue($submission, $field->key);

            return is_string($value) && $value !== '' ? $value : null;
        }

        return null;
    }

    private function payloadValue(Submission $submission, string $key): mixed
    {
        $payload = $submission->payload->values;

        return Arr::get($payload, $key);
    }

    private function defaultSuccessUrl(Submission $submission): string
    {
        return URL::to($this->configuredPath('success_path', '/payments/form/success') . '?submission=' . $this->submissionKey($submission));
    }

    private function defaultCancelUrl(Submission $submission): string
    {
        return URL::to($this->configuredPath('cancel_path', '/payments/form/cancel') . '?submission=' . $this->submissionKey($submission));
    }

    private function configuredPath(string $key, string $default): string
    {
        $path = config('capell-payments.form_builder.' . $key, $default);

        return is_string($path) ? $path : $default;
    }

    private function submissionKey(Submission $submission): string
    {
        $key = $submission->getKey();
        if (! is_int($key) && ! is_string($key)) {
            throw new UnexpectedValueException('Submission keys must be integers or strings.');
        }

        return (string) $key;
    }
}

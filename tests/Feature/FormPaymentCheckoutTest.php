<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction;
use Capell\FormBuilder\Actions\ResolveFormPaymentCheckoutDataAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Contracts\PaymentGateway;
use Capell\Payments\Data\CheckoutSessionData;
use Capell\Payments\Enums\CheckoutMode;
use Capell\Payments\Enums\CheckoutSessionStatus;
use Capell\Payments\Enums\PaymentProvider;
use Capell\Payments\Enums\PaymentPurpose;
use Capell\Payments\Models\CheckoutSession;
use Capell\Payments\Tests\Fakes\FakePaymentGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('app.url', 'https://example.test');
    config()->set('capell-payments.form_builder.allowed_return_hosts', ['example.test']);
    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');
});

it('creates form payment checkout sessions from portable Form Builder payment fields', function (): void {
    $form = paymentsFormBuilderForm();
    $submission = paymentsFormBuilderSubmission($form, [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);
    $gateway = new FakePaymentGateway(new CheckoutSessionData(
        provider: PaymentProvider::Stripe,
        providerSessionId: 'cs_form_payment_123',
        status: CheckoutSessionStatus::Open,
        mode: CheckoutMode::Payment,
        purpose: PaymentPurpose::FormPayment,
        url: 'https://checkout.stripe.com/c/pay/cs_form_payment_123',
        currency: 'gbp',
        amountSubtotal: 3500,
        amountTotal: 3500,
        customerEmail: 'buyer@example.test',
        metadata: [
            'form_id' => (string) $form->getKey(),
            'submission_id' => (string) $submission->getKey(),
            'field_key' => 'donation',
        ],
        providerPayload: ['id' => 'cs_form_payment_123'],
    ));

    app()->instance(PaymentGateway::class, $gateway);

    $checkoutSession = CreateFormPaymentCheckoutSessionAction::run(
        submission: $submission,
        successUrl: 'https://example.test/thanks',
        cancelUrl: 'https://example.test/retry',
    );

    expect($checkoutSession)->toBeInstanceOf(CheckoutSession::class)
        ->and($checkoutSession->purpose)->toBe(PaymentPurpose::FormPayment)
        ->and($checkoutSession->source_type)->toBe('form_builder.form')
        ->and($checkoutSession->source_id)->toBe((string) $form->getKey())
        ->and($checkoutSession->payable_type)->toBe('form_builder.submission')
        ->and($checkoutSession->payable_id)->toBe((string) $submission->getKey())
        ->and($checkoutSession->reference_id)->toBe('form-submission-' . $submission->getKey())
        ->and($checkoutSession->amount_total)->toBe(3500)
        ->and($gateway->lastRequest?->customerEmail)->toBe('buyer@example.test')
        ->and($gateway->lastRequest?->lineItems[0]->name)->toBe('Donation')
        ->and($gateway->lastRequest?->lineItems[0]->amount)->toBe(3500)
        ->and($gateway->lastRequest?->lineItems[0]->currency)->toBe('gbp')
        ->and($gateway->lastRequest?->idempotencyKey)->toBe('form-payment-' . $submission->getKey() . '-donation');
});

it('uses configured payment field amount before submitted amount', function (): void {
    $form = paymentsFormBuilderForm([
        [
            'key' => 'fixed_payment',
            'label' => 'Application fee',
            'type' => 'payment',
            'required' => true,
            'payment_amount_cents' => 1200,
            'payment_currency' => 'usd',
        ],
    ]);
    $submission = paymentsFormBuilderSubmission($form, [
        'fixed_payment' => 9999,
    ]);

    $paymentData = ResolveFormPaymentCheckoutDataAction::run($submission);

    expect($paymentData->amountCents)->toBe(1200)
        ->and($paymentData->currency)->toBe('usd')
        ->and($paymentData->successUrl)->toContain('/payments/form/success?submission=' . $submission->getKey())
        ->and($paymentData->cancelUrl)->toContain('/payments/form/cancel?submission=' . $submission->getKey());
});

it('rejects form payment checkout creation when the payment amount is invalid', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 0,
    ]);

    ResolveFormPaymentCheckoutDataAction::run($submission);
})->throws(ValidationException::class);

it('creates signed public checkout URLs for form payment submissions', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    $url = CreateFormPaymentCheckoutUrlAction::run(
        submission: $submission,
        successUrl: 'https://example.test/thanks',
        cancelUrl: 'https://example.test/retry',
        ttlMinutes: 15,
    );

    expect($url)->toContain('/capell/payments/forms/' . $submission->getKey() . '/checkout')
        ->and($url)->toContain('success_url=')
        ->and($url)->toContain('cancel_url=')
        ->and($url)->toContain('signature=')
        ->and($url)->not->toContain('capell-app/payments')
        ->and($url)->not->toContain('Filament');
});

it('rejects form payment checkout return URLs outside the allowed hosts', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    CreateFormPaymentCheckoutUrlAction::run(
        submission: $submission,
        successUrl: 'https://attacker.example/thanks',
        cancelUrl: 'https://example.test/retry',
        ttlMinutes: 15,
    );
})->throws(ValidationException::class);

it('rejects private and internal form payment return URLs even when configured', function (string $returnUrl): void {
    config()->set('capell-payments.form_builder.allowed_return_hosts', [
        '127.0.0.1',
        '10.0.0.25',
        '172.16.0.25',
        '192.168.1.25',
        '169.254.169.254',
        '[::1]',
        'localhost',
        'example.test',
    ]);

    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    CreateFormPaymentCheckoutUrlAction::run(
        submission: $submission,
        successUrl: $returnUrl,
        cancelUrl: 'https://example.test/retry',
        ttlMinutes: 15,
    );
})->with([
    'loopback IPv4' => ['http://127.0.0.1/thanks'],
    'private class A' => ['https://10.0.0.25/thanks'],
    'private class B' => ['https://172.16.0.25/thanks'],
    'private class C' => ['https://192.168.1.25/thanks'],
    'link-local metadata' => ['http://169.254.169.254/latest/meta-data'],
    'loopback IPv6' => ['http://[::1]/thanks'],
    'localhost' => ['http://localhost/thanks'],
])->throws(ValidationException::class);

it('normalizes local form payment return paths before sending them to the provider', function (): void {
    $form = paymentsFormBuilderForm();
    $submission = paymentsFormBuilderSubmission($form, [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);
    $gateway = new FakePaymentGateway(new CheckoutSessionData(
        provider: PaymentProvider::Stripe,
        providerSessionId: 'cs_form_payment_local_paths',
        status: CheckoutSessionStatus::Open,
        mode: CheckoutMode::Payment,
        purpose: PaymentPurpose::FormPayment,
        url: 'https://checkout.stripe.com/c/pay/cs_form_payment_local_paths',
        currency: 'gbp',
        amountSubtotal: 3500,
        amountTotal: 3500,
        customerEmail: 'buyer@example.test',
        providerPayload: ['id' => 'cs_form_payment_local_paths'],
    ));

    app()->instance(PaymentGateway::class, $gateway);
    $submissionKey = $submission->getKey();
    throw_unless(is_int($submissionKey) || is_string($submissionKey), RuntimeException::class, 'Expected form submission key.');

    CreateFormPaymentCheckoutSessionAction::run(
        submission: $submission,
        successUrl: '/thanks?submission=' . $submissionKey,
        cancelUrl: '/retry',
    );

    expect($gateway->lastRequest?->successUrl)->toBe('https://example.test/thanks?submission=' . $submissionKey)
        ->and($gateway->lastRequest?->cancelUrl)->toBe('https://example.test/retry');
});

it('passes configured allowed form payment return URLs to the provider checkout request', function (): void {
    config()->set('capell-payments.form_builder.allowed_return_hosts', ['payments.example.test']);

    $form = paymentsFormBuilderForm();
    $submission = paymentsFormBuilderSubmission($form, [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);
    $gateway = new FakePaymentGateway(new CheckoutSessionData(
        provider: PaymentProvider::Stripe,
        providerSessionId: 'cs_form_payment_allowed_host',
        status: CheckoutSessionStatus::Open,
        mode: CheckoutMode::Payment,
        purpose: PaymentPurpose::FormPayment,
        url: 'https://checkout.stripe.com/c/pay/cs_form_payment_allowed_host',
        currency: 'gbp',
        amountSubtotal: 3500,
        amountTotal: 3500,
        customerEmail: 'buyer@example.test',
        providerPayload: ['id' => 'cs_form_payment_allowed_host'],
    ));

    app()->instance(PaymentGateway::class, $gateway);

    CreateFormPaymentCheckoutSessionAction::run(
        submission: $submission,
        successUrl: 'https://payments.example.test/thanks',
        cancelUrl: 'https://payments.example.test/retry',
    );

    expect($gateway->lastRequest?->successUrl)->toBe('https://payments.example.test/thanks')
        ->and($gateway->lastRequest?->cancelUrl)->toBe('https://payments.example.test/retry');
});

it('redirects signed form payment checkout requests to the provider checkout URL', function (): void {
    $form = paymentsFormBuilderForm();
    $submission = paymentsFormBuilderSubmission($form, [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);
    $gateway = new FakePaymentGateway(new CheckoutSessionData(
        provider: PaymentProvider::Stripe,
        providerSessionId: 'cs_form_payment_route_123',
        status: CheckoutSessionStatus::Open,
        mode: CheckoutMode::Payment,
        purpose: PaymentPurpose::FormPayment,
        url: 'https://checkout.stripe.com/c/pay/cs_form_payment_route_123',
        currency: 'gbp',
        amountSubtotal: 3500,
        amountTotal: 3500,
        customerEmail: 'buyer@example.test',
        providerPayload: ['id' => 'cs_form_payment_route_123'],
    ));

    app()->instance(PaymentGateway::class, $gateway);

    $response = $this->get(CreateFormPaymentCheckoutUrlAction::run(
        submission: $submission,
        successUrl: 'https://example.test/thanks',
        cancelUrl: 'https://example.test/retry',
    ));

    $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_form_payment_route_123');

    expect($gateway->lastRequest?->successUrl)->toBe('https://example.test/thanks')
        ->and($gateway->lastRequest?->cancelUrl)->toBe('https://example.test/retry')
        ->and(CheckoutSession::query()->where('provider_session_id', 'cs_form_payment_route_123')->exists())->toBeTrue();
});

it('rejects unsigned public form payment checkout requests', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    $this
        ->get(route('capell-payments.form-builder.checkout', ['submission' => $submission]))
        ->assertForbidden();
});

/**
 * @param  list<array<string, mixed>>|null  $paymentFields
 */
function paymentsFormBuilderForm(?array $paymentFields = null): Form
{
    $siteId = (int) Site::factory()->create()->getKey();

    return Form::query()->create([
        'site_id' => $siteId,
        'name' => 'Donation form',
        'handle' => 'donation-form-' . str()->random(8),
        'description' => null,
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
            ...($paymentFields ?? [
                [
                    'key' => 'donation',
                    'label' => 'Donation',
                    'type' => 'payment',
                    'required' => true,
                    'payment_currency' => 'gbp',
                ],
            ]),
        ],
        'settings' => [],
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $values
 */
function paymentsFormBuilderSubmission(Form $form, array $values): Submission
{
    return Submission::query()->create([
        'form_id' => $form->getKey(),
        'site_id' => $form->site_id,
        'payload' => new SubmissionPayloadData($values),
        'meta' => new SubmissionMetaData(url: 'https://example.test/donate'),
        'status' => 'new',
        'submitted_at' => now(),
    ]);
}

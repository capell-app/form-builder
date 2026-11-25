<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction;
use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction;
use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\ResolveFormPaymentCheckoutDataAction;
use Capell\FormBuilder\Bridges\Payments\CreateFormPaymentCheckoutSessionAction;
use Capell\FormBuilder\Contracts\FormPaymentCheckoutRail;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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
            'form_id' => formPaymentCheckoutTestKey($form->getKey()),
            'submission_id' => formPaymentCheckoutTestKey($submission->getKey()),
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
        ->and($checkoutSession->source_id)->toBe(formPaymentCheckoutTestKey($form->getKey()))
        ->and($checkoutSession->payable_type)->toBe('form_builder.submission')
        ->and($checkoutSession->payable_id)->toBe(formPaymentCheckoutTestKey($submission->getKey()))
        ->and($checkoutSession->reference_id)->toBe('form-submission-' . formPaymentCheckoutTestKey($submission->getKey()))
        ->and($checkoutSession->amount_total)->toBe(3500)
        ->and($gateway->lastRequest?->customerEmail)->toBe('buyer@example.test')
        ->and($gateway->lastRequest?->lineItems[0]->name)->toBe('Donation')
        ->and($gateway->lastRequest?->lineItems[0]->amount)->toBe(3500)
        ->and($gateway->lastRequest?->lineItems[0]->currency)->toBe('gbp')
        ->and($gateway->lastRequest?->idempotencyKey)->toBe('form-payment-' . formPaymentCheckoutTestKey($submission->getKey()) . '-donation');
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
        ->and($paymentData->successUrl)->toContain('/payments/form/success?submission=' . formPaymentCheckoutTestKey($submission->getKey()))
        ->and($paymentData->cancelUrl)->toContain('/payments/form/cancel?submission=' . formPaymentCheckoutTestKey($submission->getKey()));
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

    expect($url)->toContain('/capell/payments/forms/' . formPaymentCheckoutTestKey($submission->getKey()) . '/checkout')
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

it('rejects stale signed checkout URLs when Payments is disabled', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    $checkoutUrl = CreateFormPaymentCheckoutUrlAction::run($submission);

    CapellCore::forcePackageInstalled('capell-app/payments', false);

    $this->get($checkoutUrl)->assertNotFound();
});

it('does not create payment checkout redirects when the Payments table is unavailable', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), [
        'email' => 'buyer@example.test',
        'donation' => 3500,
    ]);

    // Other tables hold foreign keys to the sessions table; MariaDB refuses the drop unless they are suspended.
    Schema::withoutForeignKeyConstraints(static fn () => Schema::drop('payment_checkout_sessions'));

    expect(CreateFormPaymentCheckoutRedirectUrlAction::run($submission))->toBeNull();
});

it('does not send visitors to checkout when the payment field was hidden by its conditions', function (): void {
    $form = paymentsFormBuilderForm([
        ['key' => 'service', 'label' => 'Service', 'type' => 'text', 'required' => true],
        [
            'key' => 'deposit',
            'label' => 'Deposit',
            'type' => 'payment',
            'payment_currency' => 'gbp',
            'payment_amount' => 5000,
            'visibility_conditions' => [['field_key' => 'service', 'operator' => 'equals', 'value' => 'priority']],
        ],
    ]);

    $hidden = paymentsFormBuilderSubmission($form, ['email' => 'buyer@example.test', 'service' => 'standard']);
    $shown = paymentsFormBuilderSubmission($form, ['email' => 'buyer@example.test', 'service' => 'priority']);

    expect(CreateFormPaymentCheckoutRedirectUrlAction::run($hidden))->toBeNull()
        ->and(CreateFormPaymentCheckoutRedirectUrlAction::run($shown))->not->toBeNull();
});

it('does not create payment checkout redirects for spam submissions', function (): void {
    $submission = paymentsFormBuilderSubmission(paymentsFormBuilderForm(), ['email' => 'bot@example.test', 'donation' => 3500]);
    $submission->forceFill(['status' => SubmissionStatus::Spam])->save();

    expect(CreateFormPaymentCheckoutRedirectUrlAction::run($submission))->toBeNull();
});

it('pins the optional checkout rail at submission creation and fails closed when it becomes unavailable', function (): void {
    Mail::fake();
    Queue::fake();
    CapellCore::forcePackageInstalled('capell-app/payments', false);
    $rail = new FixtureFormCheckoutRail('fixture-checkout');
    app()->instance(FormPaymentCheckoutRail::class, $rail);
    $form = paymentsFormBuilderForm();
    $meta = new SubmissionMetaData(url: 'https://example.test/donate');
    $values = ['email' => 'buyer@example.test', 'donation' => 3500, 'payment_rail' => 'attacker-rail'];
    $legacy = CreateSubmissionAction::run($form, $values, $meta);
    $rail->enabled = true;
    $ordinary = CreateSubmissionAction::run(paymentsFormBuilderForm([]), ['email' => 'buyer@example.test'], $meta);
    $hiddenPayment = paymentsFormBuilderForm([[
        'key' => 'donation',
        'label' => 'Donation',
        'type' => 'payment',
        'required' => true,
        'payment_amount_cents' => 3500,
        'visibility_conditions' => [['field_key' => 'email', 'operator' => 'equals', 'value' => 'pay@example.test']],
    ]]);
    $hidden = CreateSubmissionAction::run($hiddenPayment, $values, $meta);
    $selected = CreateSubmissionAction::run($form, $values, $meta);
    $selectedId = $selected->getKey();
    expect($ordinary->refresh()->paymentRail())->toBeNull()
        ->and($hidden->refresh()->paymentRail())->toBeNull()
        ->and($legacy->refresh()->paymentRail())->toBeNull()
        ->and($selected->refresh()->paymentRail())->toBe('fixture-checkout')
        ->and($selected->toArray())->not->toHaveKey('payment_rail')
        ->and($rail->selectionCalls)->toBe(2)
        ->and($rail->checkoutCalls)->toBe(0);

    $url = CreateFormPaymentCheckoutRedirectUrlAction::run($selected);
    expect($url)->toBeString()->toContain('signature=');
    if (! is_string($url)) {
        throw new RuntimeException('Expected a signed checkout URL.');
    }

    $this->get(route('capell-payments.form-builder.checkout', ['submission' => $selected]))->assertForbidden();
    $this->get($url . '&payment_rail=other')->assertForbidden();
    expect($rail->checkoutCalls)->toBe(0);

    $response = $this->get($url)->assertRedirect('https://checkout.example.test/session');
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
        ->and($rail->lastSubmission)->toBe($selectedId);
    $this->get($url)->assertRedirect('https://checkout.example.test/session');
    expect($rail->checkoutCalls)->toBe(2);

    $rail->enabled = false;
    $this->get($url)->assertServiceUnavailable();
    $rail->enabled = true;
    $rail->url = 'http://checkout.example.test/session';
    $this->get($url)->assertServiceUnavailable();
    expect($rail->checkoutCalls)->toBe(4);

    CapellCore::forcePackageInstalled('capell-app/payments', true);
    $other = new FixtureFormCheckoutRail('another-rail');
    app()->instance(FormPaymentCheckoutRail::class, $other);
    $this->get($url)->assertServiceUnavailable();
    app()->offsetUnset(FormPaymentCheckoutRail::class);
    $this->get($url)->assertServiceUnavailable();
    expect($other->checkoutCalls)->toBe(0)
        ->and(CheckoutSession::query()->count())->toBe(0)
        ->and($selected->refresh()->paymentRail())->toBe('fixture-checkout');
    expect(fn () => CreateFormPaymentCheckoutSessionAction::run($selected))->toThrow(LogicException::class, 'different payment rail');
    expect(fn (): bool => $selected->forceFill(['payment_rail' => null])->save())->toThrow(LogicException::class, 'cannot change');

    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_11_19_000001_add_payment_rail_to_submissions_table.php';
    $migration->down();
    $migration->up();

    expect($selected->refresh()->paymentRail())->toBe('fixture-checkout');

    // A previously accepted legacy submission remains on its original rail after opt-in.
    $rail->url = 'https://checkout.example.test/session';
    app()->instance(FormPaymentCheckoutRail::class, $rail);
    $legacyGateway = new FakePaymentGateway(new CheckoutSessionData(
        provider: PaymentProvider::Stripe,
        providerSessionId: 'legacy-rail-replay',
        status: CheckoutSessionStatus::Open,
        mode: CheckoutMode::Payment,
        purpose: PaymentPurpose::FormPayment,
        url: 'https://checkout.stripe.com/legacy-rail-replay',
        currency: 'gbp',
        amountSubtotal: 3500,
        amountTotal: 3500,
    ));
    app()->instance(PaymentGateway::class, $legacyGateway);
    $this->get(CreateFormPaymentCheckoutUrlAction::run($legacy))->assertRedirect('https://checkout.stripe.com/legacy-rail-replay');
    expect($legacyGateway->lastRequest)->not->toBeNull()
        ->and($rail->checkoutCalls)->toBe(4)
        ->and($rail->selectionCalls)->toBe(2);
});

it('does not charge a fixed fee hidden from an accepted submission', function (): void {
    Mail::fake();
    Queue::fake();
    $rail = new FixtureFormCheckoutRail('fixture-checkout');
    $rail->enabled = true;

    app()->instance(FormPaymentCheckoutRail::class, $rail);
    $form = paymentsFormBuilderForm([[
        'key' => 'fee', 'label' => 'Fee', 'type' => 'payment', 'required' => true,
        'payment_amount_cents' => 3500, 'payment_currency' => 'gbp',
        'visibility_conditions' => [['field_key' => 'email', 'operator' => 'equals', 'value' => 'pay@example.test']],
    ]]);
    $submission = CreateSubmissionAction::run($form, ['email' => 'free@example.test', 'fee' => 3500], new SubmissionMetaData);
    expect($submission->paymentRail())->toBeNull();
    expect(CreateFormPaymentCheckoutRedirectUrlAction::run($submission))->toBeNull();
    expect(fn () => ResolveFormPaymentCheckoutDataAction::run($submission))->toThrow(ValidationException::class);
    expect(fn () => CreateFormPaymentCheckoutSessionAction::run($submission))->toThrow(ValidationException::class);
    expect(CheckoutSession::query()->count())->toBe(0);

    $payable = paymentsFormBuilderSubmission($form, ['email' => 'pay@example.test']);
    expect(CreateFormPaymentCheckoutRedirectUrlAction::run($payable))->toBeString()
        ->and(ResolveFormPaymentCheckoutDataAction::run($payable)->amountCents)->toBe(3500);
});

it('keeps selected checkout signatures separate from the predecessor legacy route', function (): void {
    Mail::fake();
    Queue::fake();
    $rail = new FixtureFormCheckoutRail('fixture-checkout');
    $rail->enabled = true;

    app()->instance(FormPaymentCheckoutRail::class, $rail);
    $form = paymentsFormBuilderForm();
    $values = ['email' => 'buyer@example.test', 'donation' => 3500];
    $legacy = paymentsFormBuilderSubmission($form, $values);
    $selected = CreateSubmissionAction::run($form, $values, new SubmissionMetaData(url: 'https://example.test/donate'));

    $selectedUrl = CreateFormPaymentCheckoutUrlAction::run($selected);
    expect(parse_url($selectedUrl, PHP_URL_PATH))->toEndWith('/selected-checkout');

    $oldUrl = str_replace('/selected-checkout?', '/checkout?', $selectedUrl);
    $this->get($oldUrl)->assertForbidden();
    $this->get(URL::temporarySignedRoute('capell-payments.form-builder.checkout', now()->addMinute(), ['submission' => $selected]))->assertNotFound();
    $this->get(URL::temporarySignedRoute('capell-payments.form-builder.selected-checkout', now()->addMinute(), ['submission' => $legacy]))->assertNotFound();
    expect($rail->checkoutCalls)->toBe(0);
    $this->get($selectedUrl)->assertRedirect('https://checkout.example.test/session');
});

it('rejects an incompatible retained payment rail column instead of treating it as installed', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_11_19_000001_add_payment_rail_to_submissions_table.php';
    Schema::table('submissions', static fn (Blueprint $table) => $table->dropColumn('payment_rail'));
    DB::statement("ALTER TABLE submissions ADD COLUMN payment_rail VARCHAR(64) DEFAULT 'legacy'");

    expect(fn () => $migration->up())->toThrow(LogicException::class, 'unexpected shape');
});

final class FixtureFormCheckoutRail implements FormPaymentCheckoutRail
{
    public bool $enabled = false;

    public int $selectionCalls = 0;

    public int $checkoutCalls = 0;

    public mixed $lastSubmission = null;

    public string $url = 'https://checkout.example.test/session';

    public function __construct(private readonly string $rail) {}

    #[Override]
    public function key(): string
    {
        return $this->rail;
    }

    #[Override]
    public function selects(Submission $submission): bool
    {
        expect($submission->exists)->toBeFalse();
        $this->selectionCalls++;

        return $this->enabled;
    }

    #[Override]
    public function checkoutUrl(Submission $submission): ?string
    {
        expect($submission->exists)->toBeTrue();
        $this->checkoutCalls++;
        $this->lastSubmission = $submission->getKey();

        return $this->enabled ? $this->url : null;
    }
}

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

function formPaymentCheckoutTestKey(mixed $key): string
{
    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected an integer or string model key.');
    }

    return (string) $key;
}

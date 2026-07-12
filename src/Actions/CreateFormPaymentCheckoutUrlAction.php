<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Actions\ValidateFormPaymentReturnUrlAction;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static string run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null, ?int $ttlMinutes = null)
 */
final class CreateFormPaymentCheckoutUrlAction
{
    use AsAction;

    public function handle(
        Submission $submission,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?int $ttlMinutes = null,
    ): string {
        $ttl = $ttlMinutes ?? (int) config('capell-payments.form_builder.checkout_url_ttl_minutes', 60);
        $successUrl = ValidateFormPaymentReturnUrlAction::run($successUrl);
        $cancelUrl = ValidateFormPaymentReturnUrlAction::run($cancelUrl);

        return URL::temporarySignedRoute(
            name: 'capell-payments.form-builder.checkout',
            expiration: now()->addMinutes(max(1, $ttl)),
            parameters: array_filter([
                'submission' => $submission->getKey(),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ], static fn (?string $value): bool => $value !== null && $value !== ''),
        );
    }
}

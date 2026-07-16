<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Capell\Payments\Actions\ValidateFormPaymentReturnUrlAction;
use Illuminate\Support\Facades\URL;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static string run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null, ?int $ttlMinutes = null)
 */
final class CreateFormPaymentCheckoutUrlAction
{
    use AsFake;
    use AsObject;

    public function handle(
        Submission $submission,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?int $ttlMinutes = null,
    ): string {
        if (! IsFormPaymentIntegrationAvailableAction::run()) {
            throw new LogicException('The Payments integration is not available.');
        }

        $configuredTtl = config('capell-payments.form_builder.checkout_url_ttl_minutes', 60);
        $ttl = $ttlMinutes ?? (is_numeric($configuredTtl) ? (int) $configuredTtl : 60);
        $successUrl = ValidateFormPaymentReturnUrlAction::run($successUrl);
        $cancelUrl = ValidateFormPaymentReturnUrlAction::run($cancelUrl);

        $successUrl = is_string($successUrl) ? $successUrl : null;
        $cancelUrl = is_string($cancelUrl) ? $cancelUrl : null;

        return URL::temporarySignedRoute(
            name: 'capell-payments.form-builder.checkout',
            expiration: now()->addMinutes(max(1, $ttl)),
            parameters: array_filter([
                'submission' => $submission->getKey(),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }
}

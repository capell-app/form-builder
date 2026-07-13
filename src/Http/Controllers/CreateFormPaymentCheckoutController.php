<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Http\Controllers;

use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction;
use Capell\FormBuilder\Actions\IsFormPaymentIntegrationAvailableAction;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CreateFormPaymentCheckoutController
{
    public function __invoke(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless(IsFormPaymentIntegrationAvailableAction::run(), 404);

        $checkoutSession = CreateFormPaymentCheckoutSessionAction::run(
            submission: $submission,
            successUrl: $this->urlParameter($request, 'success_url'),
            cancelUrl: $this->urlParameter($request, 'cancel_url'),
        );

        abort_if($checkoutSession->url === null || $checkoutSession->url === '', 502);

        return redirect()->away($checkoutSession->url);
    }

    private function urlParameter(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

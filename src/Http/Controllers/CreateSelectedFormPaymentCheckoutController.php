<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Http\Controllers;

use Capell\FormBuilder\Actions\ResolveFormPaymentCheckoutRailAction;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Http\RedirectResponse;

final class CreateSelectedFormPaymentCheckoutController
{
    private const array NO_STORE_HEADERS = ['Cache-Control' => 'private, no-store'];

    public function __invoke(Submission $submission): RedirectResponse
    {
        $rail = $submission->paymentRail();
        abort_if($rail === null, 404);

        $checkout = ResolveFormPaymentCheckoutRailAction::run($rail);
        abort_if($checkout === null, 503, '', self::NO_STORE_HEADERS);

        $url = $checkout->checkoutUrl($submission);
        abort_unless(is_string($url) && $this->isHostedCheckoutUrl($url), 503, '', self::NO_STORE_HEADERS);

        return redirect()->away($url, headers: self::NO_STORE_HEADERS);
    }

    private function isHostedCheckoutUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }
}

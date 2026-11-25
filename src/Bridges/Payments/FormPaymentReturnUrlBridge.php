<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Bridges\Payments;

use Capell\Core\Facades\CapellCore;
use Capell\Payments\Actions\ValidateFormPaymentReturnUrlAction;

/**
 * Payments' return-URL check, for callers that have already confirmed the Payments
 * integration is available. Form Builder reaches it only through this class.
 */
final class FormPaymentReturnUrlBridge
{
    public static function validate(?string $url): ?string
    {
        if (! CapellCore::isPackageAvailable('capell-app/payments')) {
            return null;
        }

        return ValidateFormPaymentReturnUrlAction::run($url);
    }
}

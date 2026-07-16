<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Facades\CapellCore;
use Capell\Payments\Actions\CreateCheckoutSessionAction;
use Capell\Payments\Actions\ValidateFormPaymentReturnUrlAction;
use Capell\Payments\Models\CheckoutSession;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class IsFormPaymentIntegrationAvailableAction
{
    use AsFake;
    use AsObject;

    public function handle(): bool
    {
        return CapellCore::isPackageInstalled('capell-app/payments')
            && Schema::hasTable('payment_checkout_sessions')
            && class_exists(CreateCheckoutSessionAction::class)
            && class_exists(ValidateFormPaymentReturnUrlAction::class)
            && class_exists(CheckoutSession::class);
    }
}

<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Contracts\FormPaymentCheckoutRail;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ResolveFormPaymentCheckoutRailAction
{
    use AsFake;
    use AsObject;

    public function handle(?string $selected = null): ?FormPaymentCheckoutRail
    {
        if (! app()->bound(FormPaymentCheckoutRail::class)) {
            return null;
        }

        $rail = resolve(FormPaymentCheckoutRail::class);
        $key = $rail->key();
        if (preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $key) !== 1 || ($selected !== null && $selected !== $key)) {
            return null;
        }

        return $rail;
    }
}

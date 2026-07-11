<?php

declare(strict_types=1);

use Capell\FormBuilder\Http\Controllers\CreateFormPaymentCheckoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('capell/payments/forms')
    ->name('capell-payments.form-builder.')
    ->middleware(['web', 'signed'])
    ->group(function (): void {
        Route::get('{submission}/checkout', CreateFormPaymentCheckoutController::class)
            ->name('checkout');
    });

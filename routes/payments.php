<?php

declare(strict_types=1);

use Capell\FormBuilder\Http\Controllers\CreateFormPaymentCheckoutController;
use Capell\FormBuilder\Http\Controllers\CreateSelectedFormPaymentCheckoutController;
use Capell\FormBuilder\Support\FormSubmissionThrottle;
use Illuminate\Support\Facades\Route;

Route::prefix('capell/payments/forms')
    ->name('capell-payments.form-builder.')
    ->middleware(['web', 'throttle:' . FormSubmissionThrottle::CHECKOUT, 'signed'])
    ->group(function (): void {
        Route::get('{submission}/checkout', CreateFormPaymentCheckoutController::class)
            ->name('checkout');

        Route::get('{submission}/selected-checkout', CreateSelectedFormPaymentCheckoutController::class)
            ->name('selected-checkout');
    });

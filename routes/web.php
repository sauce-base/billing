<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingController;
use Modules\Billing\Http\Controllers\BillingPortalController;
use Modules\Billing\Http\Controllers\CheckoutController;
use Modules\Billing\Http\Middleware\RedirectToRegister;

Route::post('/billing/checkout', [CheckoutController::class, 'create'])->name('billing.checkout.create');

Route::middleware(RedirectToRegister::class)->group(function () {
    Route::get('/billing/checkout/{checkout_session}', [CheckoutController::class, 'show'])->name('billing.checkout');
    Route::post('/billing/checkout/{checkout_session}', [CheckoutController::class, 'store'])->name('billing.checkout.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/portal', BillingPortalController::class)->name('billing.portal');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingController;
use Modules\Billing\Http\Controllers\BillingPortalController;
use Modules\Billing\Http\Controllers\CheckoutController;

// Guest-accessible
Route::post('/billing/checkout', [CheckoutController::class, 'create'])->name('billing.checkout.create');
Route::get('/billing/checkout/{checkout_session}', [CheckoutController::class, 'show'])->name('billing.checkout');
Route::post('/billing/checkout/{checkout_session}', [CheckoutController::class, 'store'])->name('billing.checkout.store');

// Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/portal', BillingPortalController::class)->name('billing.portal');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingController;
use Modules\Billing\Http\Controllers\SubscriptionController;

Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');

    Route::post('/billing/subscriptions', [SubscriptionController::class, 'store'])->name('billing.subscriptions.store');
    Route::delete('/billing/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('billing.subscriptions.destroy');
    Route::post('/billing/subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('billing.subscriptions.resume');
});

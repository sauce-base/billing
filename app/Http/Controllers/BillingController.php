<?php

namespace Modules\Billing\Http\Controllers;

use Inertia\Inertia;
use Modules\Billing\Models\Product;

class BillingController
{
    public function __construct() {}

    /**
     * Show billing dashboard.
     */
    public function index()
    {
        return Inertia::render('Billing::Index', [
            'products' => Product::all(),
        ]);
    }
}

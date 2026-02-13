<?php

use Illuminate\Support\Facades\Auth;
use Modules\Billing\Models\Product;
use Spatie\Navigation\Facades\Navigation;
use Spatie\Navigation\Section;

/*
|--------------------------------------------------------------------------
| Billing Module Navigation
|--------------------------------------------------------------------------
|
| Define Billing module navigation items here.
| These items will be loaded automatically when the module is enabled.
|
*/

// Landing Page Navigation
Navigation::addIf(Product::displayable()->count(), 'Pricing', '/#pricing', function (Section $section) {
    $section->attributes([
        'group' => 'landing',
        'slug' => 'pricing',
        'external' => true,
        'order' => 1,
    ]);
});

// User menu - Upgrade
Navigation::addIf(! Auth::user()?->isSubscriber(), 'Upgrade', '/#pricing', function (Section $section) {
    $section->attributes([
        'group' => 'user',
        'slug' => 'upgrade',
        'order' => 0,
    ]);
});

// Settings sidebar - Billing
Navigation::add('Billing', route('settings.billing'), function (Section $section) {
    $section->attributes([
        'group' => 'settings',
        'slug' => 'billing',
        'order' => 30,
    ]);
});

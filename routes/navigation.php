<?php

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

Navigation::add('Billing', route('billing.index'), function (Section $section) {
    $section->attributes([
        'group' => 'main',
        'badge' => [
            'content' => 'New',
            'variant' => 'info',
        ],
    ]);
});

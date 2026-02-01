<?php

namespace Modules\Billing\Enums;

enum BillingScheme: string
{
    case FlatAmount = 'flat_amount';
    case PerUnit = 'per_unit';
}

<?php

namespace Modules\Billing\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Voided = 'voided';
}

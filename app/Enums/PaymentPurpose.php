<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Order = 'order';
    case TopUp = 'top_up';
}

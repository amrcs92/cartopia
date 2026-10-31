<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Placed = 'placed';
    case Cancelled = 'cancelled';
}

<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Expired = 'expired';
}

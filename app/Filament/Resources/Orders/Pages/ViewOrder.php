<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<Order>
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}

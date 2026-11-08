<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Someone else's order, or one that was never placed, answers 404, so its
     * id is not confirmed to exist. A refunded order was placed, so it stays.
     */
    public function view(User $user, Order $order): Response
    {
        return $order->user_id === $user->id && in_array($order->status, [OrderStatus::Placed, OrderStatus::Refunded], true) ? Response::allow() : Response::denyAsNotFound();
    }
}

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
     * id is not confirmed to exist.
     */
    public function view(User $user, Order $order): Response
    {
        return $order->user_id === $user->id && $order->status === OrderStatus::Placed ? Response::allow() : Response::denyAsNotFound();
    }
}

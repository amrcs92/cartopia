<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * Someone else's payment answers 404, so its id is not confirmed to exist.
     */
    public function view(User $user, Payment $payment): Response
    {
        return $payment->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

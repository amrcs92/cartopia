<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class CheckoutStartController extends Controller
{
    /**
     * Login landing point for guests: sends them back to their cart.
     */
    public function __invoke(): RedirectResponse
    {
        return to_route('cart.show');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\TopUpWallet;
use App\Http\Requests\TopUpRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TopUpController extends Controller
{
    public function store(TopUpRequest $request, TopUpWallet $topUpWallet): RedirectResponse
    {
        $amount = $request->amount();

        $topUpWallet->handle($request->user(), $amount);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$amount->format()} to your balance."]);

        return redirect()->route('wallet.show');
    }
}

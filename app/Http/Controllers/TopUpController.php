<?php

namespace App\Http\Controllers;

use App\Actions\TopUpWallet;
use App\Http\Requests\TopUpRequest;
use Illuminate\Http\RedirectResponse;

class TopUpController extends Controller
{
    public function store(TopUpRequest $request, TopUpWallet $topUpWallet): RedirectResponse
    {
        $amount = $request->amount();

        $topUpWallet->handle($request->user(), $amount);

        return redirect()->route('wallet.show')
            ->with('success', "Added {$amount->format()} to your balance.");
    }
}

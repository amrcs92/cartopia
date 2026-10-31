<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletTransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public const int PER_PAGE = 15;

    public function show(Request $request): Response
    {
        $user = $request->user();

        $transactions = $user->walletTransactions()
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return Inertia::render('wallet/WalletPage', [
            'balance' => $user->fresh()->balance->format(),
            'transactions' => WalletTransactionResource::collection($transactions),
        ]);
    }
}

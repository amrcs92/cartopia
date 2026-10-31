<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionType;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public const int PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->with(['shippingMethod', 'items.product.primaryImage'])
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return Inertia::render('orders/OrdersIndexPage', [
            'orders' => OrderResource::collection($orders),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['shippingMethod', 'items.product.primaryImage']);

        return Inertia::render('orders/OrderShowPage', [
            'order' => (new OrderResource($order))->resolve(),
            'balance_after' => $order->walletTransactions()
                ->where('type', WalletTransactionType::Purchase)
                ->latest('id')
                ->first()?->balance_after->format(),
        ]);
    }
}

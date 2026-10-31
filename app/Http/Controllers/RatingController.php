<?php

namespace App\Http\Controllers;

use App\Actions\RateProduct;
use App\Http\Requests\RateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RatingController extends Controller
{
    public function update(RateProductRequest $request, Product $product, RateProduct $rateProduct): RedirectResponse
    {
        $rateProduct->handle($request->user(), $product, $request->integer('stars'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks for rating!']);

        return redirect()->back();
    }
}

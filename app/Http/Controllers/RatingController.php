<?php

namespace App\Http\Controllers;

use App\Actions\RateProduct;
use App\Http\Requests\RateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class RatingController extends Controller
{
    public function update(RateProductRequest $request, Product $product, RateProduct $rateProduct): RedirectResponse
    {
        $rateProduct->handle($request->user(), $product, $request->integer('stars'));

        return redirect()->back()->with('success', 'Thanks for rating!');
    }
}

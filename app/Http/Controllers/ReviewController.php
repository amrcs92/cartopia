<?php

namespace App\Http\Controllers;

use App\Actions\SaveReview;
use App\Http\Requests\SaveReviewRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function update(SaveReviewRequest $request, Product $product, SaveReview $saveReview): RedirectResponse
    {
        $user = $this->reviewer($request, $product);

        $review = $saveReview->handle($user, $product, $request->integer('stars'), $request->validated('body'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $review->wasRecentlyCreated ? 'Thanks for your review!' : 'Review updated.',
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $user = $this->reviewer($request, $product);

        Review::query()->whereBelongsTo($user)->whereBelongsTo($product)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review deleted.']);

        return redirect()->back();
    }

    /**
     * The signed-in customer, once the product is shown in the shop (404
     * otherwise) and they bought it (403 otherwise).
     */
    private function reviewer(Request $request, Product $product): User
    {
        abort_unless($product->is_visible, 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless(Review::canReview($user, $product), 403);

        return $user;
    }
}

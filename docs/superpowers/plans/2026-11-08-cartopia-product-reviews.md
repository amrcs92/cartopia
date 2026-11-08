# Product Reviews Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn anyone-can-rate star ratings into buyer-only reviews (stars plus optional text). They show as a list on the product page, a refund removes them, and admins can hide or delete them.

**Architecture:**
- The `ratings` table is renamed to `reviews`, with `body` and `hidden_at` added.
- One eligibility query (a Placed order holding the product) decides who can review. The review endpoints, the product page and the refund cleanup all use it.
- The product page gets a `reviews` prop, paged with Inertia partial reloads.
- The admin panel gets a Filament `ReviewResource`.

**Tech Stack:** Laravel 13, Inertia 3 + Vue 3 + TS, Tailwind 4, Filament 5.11, Pest 4 (with browser plugin).

**Spec:** `docs/superpowers/specs/2026-11-08-cartopia-product-reviews-design.md`

## Global Constraints

- Tests run as `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest`, with PHP from `/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64`.
- Pint, Larastan (level 7), `npm run lint`, `npm run format:check` and `npx vue-tsc --noEmit` must be clean.
- Never edit `.env`. Never stash, reset or force-push. Commit messages carry no AI attribution.
- Copy is exactly as written in the spec, including the curly quotes and the "8 Nov 2026" date format.
- Wayfinder helpers are generated with `php artisan wayfinder:generate --with-form`, or by `npm run build`.
- Review body: at most 1,000 characters, trimmed, empty stored as null. Stars: whole number 1–5.

## Review Focus

1. **Bypassing the eligibility check:** someone who hasn't bought the product sends `PUT products/{product}/review` directly. It must return 403. This is covered in Task 2.
2. **Partial refund ownership:** a customer has two Placed orders with the same product, and one is refunded. The review must stay. This is covered in Task 2.
3. **Hidden review in totals:** the hidden review's stars must not move the average, the count, the "Rated" filter, the minimum-rating filter or the rating sort. This is covered in Task 1.
4. **Long text without spaces:** a 1,000-character review with no spaces must wrap without the page scrolling sideways, on phones too. This is covered in Task 4.
5. **Deleted product item:** an order item whose `product_id` is null after the product was deleted must not grant reviewing to anyone. This is covered in Task 1.

---

### Task 1: Reviews table, model and eligibility

**Files:**
- Create: `database/migrations/2026_11_08_000002_turn_ratings_into_reviews.php`, `app/Models/Review.php`, `database/factories/ReviewFactory.php`, `tests/Feature/Domain/ReviewEligibilityTest.php`
- Delete: `app/Models/Rating.php`, `database/factories/RatingFactory.php`
- Modify:
  - `app/Models/Product.php`: `AVG_STARS_SQL`, `withRatingStats`, `rated`, `minRating`, and the `ratings()` relation becomes `reviews()`.
  - `app/Models/User.php`: the relation.
  - `tests/Feature/Domain/ProductScopesTest.php`, `tests/Feature/Domain/SchemaTest.php`

**Interfaces produced:**
- `Review` has the columns `user_id`, `product_id`, `stars` (int), `body` (?string), `hidden_at` (?Carbon) and timestamps.
  - Scope: `visible()` (where `hidden_at` is null).
  - Relations: `user()`, `product()`.
  - `isEdited(): bool`: `updated_at` is more than 60 seconds after `created_at`.
- `Review::canReview(User $user, Product $product): bool` returns true when an order of `$user` with status `Placed` has an `order_items` row for `$product->id`.
- `Product::reviews(): HasMany`. The rating stats count only visible reviews.
- Factory states: `hidden()`, `withBody(string)`.

- [ ] **Step 1: Write the failing tests.**
  - `canReview`:
    - true only for a `Placed` order of the same user;
    - false for `AwaitingPayment`, `Cancelled` and `Refunded` orders;
    - false for another user's order;
    - false for an order item with null `product_id`.
  - Product scopes: a hidden 1-star review must not change `rating_average`, `rating_count`, the `rated()`, `minRating(4)` and `sortBy('rating')` results.
- [ ] **Step 2: Write the migration.** It renames `ratings` to `reviews`, adds `body` (text, nullable, after `stars`) and `hidden_at` (timestamp, nullable, indexed), then deletes the reviews that fail the eligibility rule with one `DELETE … WHERE NOT EXISTS (select 1 from orders join order_items … status = 'placed')`. `down()` reverses the columns and the rename; the deleted rows aren't restored.
- [ ] **Step 3: Add the cleanup test.** Insert the rows, run the migration's cleanup query, and check that a buyer's review is kept while a non-buyer's and a refunded-only buyer's are deleted. Expose the query as a static method on the migration class, or as `Review::deleteIneligible()`, so it can be tested.
- [ ] **Step 4:** Implement the model, factory and scope changes. Run the Domain tests until they pass.
- [ ] **Step 5:** Commit "Turn product ratings into reviews from buyers", then push.

### Task 2: Review endpoints, refund cleanup, old rating route removed

**Files:**
- Create: `app/Http/Controllers/ReviewController.php` (`update`, `destroy`), `app/Http/Requests/SaveReviewRequest.php`, `app/Actions/SaveReview.php`, `app/Actions/RemoveReviewsForRefundedOrder.php`, `tests/Feature/Http/ReviewTest.php`
- Delete: `app/Http/Controllers/RatingController.php`, `app/Http/Requests/RateProductRequest.php`, `app/Actions/RateProduct.php`, `tests/Feature/Http/RatingTest.php`, `tests/Feature/Domain/RateProductTest.php`
- Modify: `routes/web.php`, `app/Actions/Admin/RefundOrder.php` (`finish()`), `tests/Feature/Admin/RefundOrderTest.php`

**Interfaces produced:**
- Routes `products.review.update` (`PUT products/{product}/review`) and `products.review.destroy` (`DELETE products/{product}/review`), both under `auth`.
- `SaveReview::handle(User, Product, int $stars, ?string $body): Review` returns the review. The caller tells "new" from "updated" with `wasRecentlyCreated`.
- `RemoveReviewsForRefundedOrder::handle(Order $order): int` returns how many reviews it deleted.

- [ ] **Step 1: Write the failing tests.**
  - A buyer creates a review with stars and body. The toast reads "Thanks for your review!". Updating reads "Review updated." Deleting reads "Review deleted."
  - A non-buyer gets 403 on PUT and DELETE. A guest is redirected to login. A hidden product returns 404.
  - Validation: stars `0`, `6`, `'abc'` and missing are rejected; a body of 1,001 characters is rejected; "  hi  " is stored as "hi"; "   " is stored as null.
  - Editing a hidden review keeps `hidden_at`.
  - The old rating route is gone: `route('products.rating.update')` doesn't exist.
- [ ] **Step 2:** Implement the request, the action, the controller and the routes. The controller does `abort_unless($product->is_visible, 404)` and `abort_unless(Review::canReview(...), 403)`. Then run the tests until they pass.
- [ ] **Step 3: Write the failing refund tests** in `RefundOrderTest`:
  - Finishing a refund deletes the customer's review of each product in the order.
  - A review stays when another `Placed` order of theirs holds the same product.
  - Another customer's review of the same product stays.
- [ ] **Step 4:** Call `RemoveReviewsForRefundedOrder` inside `finish()`'s transaction, right after the order becomes `Refunded`. Run the Admin tests until they pass.
- [ ] **Step 5:** Commit "Let only buyers review, and remove reviews on refund", then push.

### Task 3: Product page reviews prop

**Files:**
- Modify: `app/Http/Controllers/ProductController.php` (`show`), `app/Http/Resources/ProductDetailResource.php` (drop `my_rating`), `resources/js/types/catalog.ts`, `tests/Feature/Http/CatalogTest.php`
- Create: `app/Http/Resources/ReviewResource.php`, `tests/Feature/Http/ProductReviewsPropTest.php`

**Interfaces produced:** the `products/ProductShowPage` prop `reviews`:

```ts
type ReviewItem = { id: number; author: string; stars: number; body: string | null; date: string; edited: boolean };
type ProductReviews = {
    can_review: 'guest' | 'not_buyer' | 'yes';
    mine: ReviewItem | null;
    list: { data: ReviewItem[]; next_page: number | null };
};
```

- `author`: "First L." from the user's name, or just the first word when there is one word.
- `date`: `created_at` as `j M Y`.
- The list holds visible reviews with a non-null body, newest first (`created_at` desc, then `id` desc), excluding `mine`, 5 per page. The page number comes from the `reviews_page` query string.
- `list` is a merge prop (`Inertia::merge`) so a partial reload with `only: ['reviews']` appends.

- [ ] **Step 1: Write the failing tests.**
  - `can_review` for a guest, a non-buyer and a buyer.
  - `mine` is set for an author even when the review is hidden.
  - The list:
    - leaves out hidden reviews, stars-only reviews and the viewer's own;
    - orders newest first;
    - has 5 per page, with `next_page` 2, then null on the last page;
    - with `reviews_page=2`, returns the next 5.
  - The author name for "Amr Saleh" is "Amr S.", for "Cher" it's "Cher", for "  ada   lovelace " it's "Ada L.". Capitalise the first letters.
  - `edited` is true only after a real edit.
- [ ] **Step 2:** Implement it, then run the tests until they pass.
- [ ] **Step 3:** Commit "Send product reviews to the product page", then push.

### Task 4: Reviews section on the product page

**Files:**
- Create: `resources/js/modules/products/reviews/ReviewsSection.vue`, `ReviewForm.vue`, `ReviewItem.vue`, `StarPicker.vue` (made from `RatingInput.vue`'s radio group, where choosing doesn't save)
- Delete: `resources/js/modules/products/RatingInput.vue`
- Modify: `resources/js/pages/products/ProductShowPage.vue`, `tests/Browser/RedesignTest.php` (the "rates a product" test becomes the review flow)
- Create: `tests/Browser/ProductReviewsTest.php`

**Behaviour:** as in spec §5.
- The form posts with Inertia `useForm` to `products.review.update`, using `preserveScroll` and `only: ['product', 'reviews']`.
- Delete uses the existing dialog component, or a confirm modal in the store's style if there isn't one.
- **Show more reviews** runs `router.reload({ only: ['reviews'], data: { reviews_page: next_page }, preserveUrl: true })`.
- Text renders with `whitespace-pre-line wrap-anywhere`.

- [ ] **Step 1: Write the failing browser tests.**
  - A buyer picks 4 stars, types text and posts. The review shows as theirs with Edit and Delete.
  - Edit changes it to 5 stars and the summary updates. Delete → "Delete your review?" → Delete brings the empty form back.
  - A signed-in non-buyer sees "Only customers who bought this product can review it."
  - A guest sees the log-in line.
  - Seven written reviews show 5, then **Show more reviews** shows all 7, and the button goes away.
  - A 1,000-character review with no spaces at 360px wide doesn't make the page scroll sideways.
- [ ] **Step 2:** Implement the components. Check them in the browser at 1280px and 375px, light and dark.
- [ ] **Step 3:** Run the browser tests until they pass, along with lint, format and vue-tsc.
- [ ] **Step 4:** Commit "Show reviews and the review form on the product page", then push.

### Task 5: Reviews screen in the admin panel

**Files:**
- Create: `app/Filament/Resources/Reviews/ReviewResource.php`, `Pages/ListReviews.php`, `tests/Feature/Admin/ReviewResourceTest.php`
- Modify: `tests/Feature/Admin/PanelAccessTest.php` (a shopper gets 404 at `/admin/reviews`), `README.md` (one line in the Admin panel section)

**Behaviour:** as in spec §6.
- Navigation icon `heroicon-o-chat-bubble-left-right`, sort 50, between Customers (40) and Store settings (90).
- Hide and Unhide use `$review->forceFill(['hidden_at' => …])->saveQuietly()` with `timestamps = false`, so `updated_at` doesn't change.

- [ ] **Step 1: Write the failing tests.**
  - The list shows the columns and the Hidden badge.
  - Filters: Hidden / Visible, and product.
  - Search by text and by customer name.
  - Hide, then Unhide, leaves `updated_at` unchanged.
  - Delete removes the review.
  - A shopper gets 404.
- [ ] **Step 2:** Implement it, then run the Admin tests until they pass.
- [ ] **Step 3:** Commit "Add a Reviews screen to the admin panel", then push.

### Finish

- [ ] Run the full suite, the browser suite, and every code check.
- [ ] Open the PR, merge it once CI is green, and delete `store/product-reviews` locally and on GitHub.
- [ ] Run `php artisan migrate` on the local database. That runs the cleanup.

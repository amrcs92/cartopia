# Cartopia: Product Reviews Design

- **Date:** 2026-11-08
- **Status:** Implemented
- **Builds on:** the core store's ratings (amrcs92/cartopia#1) and the admin panel (amrcs92/cartopia#18 to #24).

## 1. Goal and context

Today any signed-in customer can give any visible product 1 to 5 stars, whether or not they bought it. A rating is stars only, one per customer per product, and rating again replaces the stars.

This change turns ratings into **reviews from buyers**:
- Only a customer who bought the product can review it.
- A review is stars plus optional text.
- The product page lists what buyers wrote.
- Admins can hide or delete a review.

Success means:
- Nobody can rate or review a product they haven't bought, through the page or a direct request.
- A refund takes back the right to review whatever that order held.
- Shoppers see written reviews from real buyers, with the name shortened.

### Scope

**In:** the reviews table, buyer-only reviewing, removing reviews on refund, the one-time cleanup of non-buyer ratings, the Reviews section on the product page, and the Reviews screen in the admin panel.

**Out:**
- Photos in reviews.
- Replies, and helpful/unhelpful votes.
- Review emails ("How was your order?").
- Sorting or filtering the review list.
- Showing reviews anywhere but the product page.

## 2. Who can review

A customer **can review** a product when they are signed in and have at least one order that:
- belongs to them,
- has status `Placed`,
- has an item for that product.

`AwaitingPayment`, `Cancelled` and `Refunded` orders don't count. This one rule (`Review::canReview(User, Product)` or an equivalent query object) is used by the policy, the page and the refund cleanup.

Admins have no exception. An admin reviews like any customer.

## 3. Data

The `ratings` table is renamed to **`reviews`**, keeping its rows and its unique (`user_id`, `product_id`) key. It gets two new columns:
- `body`: text, nullable, at most 1,000 characters, trimmed. An empty body is stored as null.
- `hidden_at`: timestamp, nullable. When set, an admin has hidden the review.

`created_at` and `updated_at` stay. A review shows **Edited** when `updated_at` is later than `created_at` by more than a minute. Hiding by an admin must not change `updated_at`, so hiding and unhiding write `hidden_at` without touching timestamps.

The `Rating` model becomes **`Review`** (with a factory). The product's average stars and rating count, used on cards, the product page, the "Rated" filter, the minimum-rating filter and the rating sort, **only count reviews where `hidden_at` is null**.

### The one-time cleanup

In the same migration, after the rename, every review whose author has no `Placed` order containing that product is **deleted**. This can't be undone. Today it affects only the local database.

## 4. Reviewing

### Endpoints

Both require sign-in and the "can review" rule. Otherwise they return 403. A hidden product returns 404, as rating does today.
- `PUT products/{product}/review` creates or updates the signed-in customer's review. It takes `stars` (required, whole number 1–5) and `body` (optional, string, at most 1,000 characters). It flashes "Thanks for your review!" when new and "Review updated." when changed.
- `DELETE products/{product}/review` deletes it and flashes "Review deleted."

Editing a hidden review keeps it hidden. The author isn't told it is hidden.

The old `PUT products/{product}/rating` route is removed.

### Refunds

When `RefundOrder` finishes, meaning the order becomes `Refunded`, the same database transaction deletes the customer's reviews of every product in that order for which they **no longer** pass the "can review" rule. A review stays if another `Placed` order of theirs holds the same product.

## 5. Product page

The current "Your rating" block is replaced by a **Reviews** section below the product details, with an `id="reviews"` anchor.

**Summary:** the average (one decimal) with stars and "N ratings", or "No ratings yet". This uses the existing `StarRating` look.

**Your review**, by visitor:
- **Guest:** "Log in to review this product.", where "Log in" is a link that returns to this page.
- **Signed in, can't review:** "Only customers who bought this product can review it."
- **Can review, no review yet:**
  - A form with a star picker (the existing five-star radio group, but choosing a star no longer saves on its own), "Your review (optional)" and a 1,000-character counter.
  - **Post review** is disabled until a star is chosen.
  - Validation errors show under the fields.
- **Has a review:**
  - Their stars and text as they appear in the list, with **Edit** and **Delete**.
  - **Edit** reopens the form filled in, with **Save** and **Cancel**.
  - **Delete** asks "Delete your review?" with **Delete** and **Keep it**.

**The list:**
- Visible reviews that have text, newest first. The customer's own review is left out, since it shows above.
- 5 at a time, with **Show more reviews** loading the next 5 into the page without a reload.
- Each review shows:
  - the author's first name and last initial ("Amr S.", or just the first name when there is one word),
  - stars,
  - the date ("8 Nov 2026"),
  - "Edited" when edited,
  - the text, keeping line breaks and showing it as plain text.
- With none: "No written reviews yet."

Reviews with stars only count in the summary but don't appear in the list.

Product cards keep showing the average and count from visible reviews.

## 6. Admin panel

A **Reviews** screen (navigation sort between Customers and Store settings) lists every review:
- **Columns:** product, customer, stars, text (shortened), date, and a "Hidden" badge.
- **Filters:** Hidden / Visible, and product.
- **Search:** text and customer name.
- **Row actions:**
  - **Hide** or **Unhide** sets or clears `hidden_at`.
  - **Delete** asks for confirmation.

There's no create or edit form. Admins don't write reviews for customers. Access follows the panel's existing admin check, including the persistent check on in-page actions.

## 7. Testing

**Feature tests:**
- The "can review" rule for each order status, someone else's order, and a deleted product item.
- Create, update and delete, plus the 403 and 404 cases.
- Validation: stars, body length, trimming, and empty body stored as null.
- The flash messages.
- Average, count, filters and sort ignore hidden reviews.
- A refund deletes reviews only where no other `Placed` order holds the product.
- The cleanup migration's query keeps buyers' ratings and deletes the rest.
- The product page props:
  - summary,
  - "can review" state,
  - own review,
  - list paging,
  - name shortening,
  - "Edited",
  - hidden reviews left out.
- Admin: list, filters, Hide/Unhide/Delete, and access refused to non-admins.

**Browser tests:**
- A buyer posts a review, edits it and deletes it.
- A non-buyer sees the "only customers who bought" message.
- **Show more reviews** loads the next page.

The existing rating tests are rewritten for reviews.

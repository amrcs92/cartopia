# Cartopia

An online store with a wallet, card and PayPal checkout, and an admin back office, rebuilt from a 2018 PHP project in Laravel 13 and Vue.

[![CI](https://github.com/amrcs92/cartopia/actions/workflows/ci.yml/badge.svg)](https://github.com/amrcs92/cartopia/actions/workflows/ci.yml)

<!-- walkthrough video: added after upload -->

<table>
  <tr>
    <td><img src="docs/screenshots/shop-light.jpg" alt="The shop, with aisle and price filters"></td>
    <td><img src="docs/screenshots/checkout-light.jpg" alt="The basket's checkout panel, with wallet, card and PayPal"></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/admin-dashboard-light.jpg" alt="The admin dashboard and its 30-day sales chart"></td>
    <td><img src="docs/screenshots/responsive.jpg" alt="A product page on desktop and on a phone"></td>
  </tr>
</table>

<details>
<summary>More screenshots</summary>

| Light                                                 | Dark                                                |
| ----------------------------------------------------- | --------------------------------------------------- |
| ![Home, light](docs/screenshots/home-light.jpg)       | ![Home, dark](docs/screenshots/home-dark.jpg)       |
| ![Product, light](docs/screenshots/product-light.jpg) | ![Product, dark](docs/screenshots/product-dark.jpg) |
| ![Reviews, light](docs/screenshots/reviews-light.jpg) | ![Reviews, dark](docs/screenshots/reviews-dark.jpg) |
| ![Basket, light](docs/screenshots/basket-light.jpg)   | ![Basket, dark](docs/screenshots/basket-dark.jpg)   |
| ![Wallet, light](docs/screenshots/wallet-light.jpg)   | ![Wallet, dark](docs/screenshots/wallet-dark.jpg)   |
| ![Orders, light](docs/screenshots/orders-light.jpg)   | ![Orders, dark](docs/screenshots/orders-dark.jpg)   |
| ![Receipt, light](docs/screenshots/receipt-light.jpg) | ![Receipt, dark](docs/screenshots/receipt-dark.jpg) |

| Admin                                                        |                                                              |
| ------------------------------------------------------------ | ------------------------------------------------------------ |
| ![Admin orders](docs/screenshots/admin-orders-light.jpg)     | ![Admin products](docs/screenshots/admin-products-light.jpg) |
| ![Admin payments](docs/screenshots/admin-payments-light.jpg) | ![Admin reviews](docs/screenshots/admin-reviews-light.jpg)   |

</details>

## Work with me

Like what you see? I build online stores and custom web applications with Laravel and Vue, from the first idea to launch. If you have a project in mind, email me at [amrcs1992@gmail.com](mailto:amrcs1992@gmail.com) and tell me about it.

## What it does

- Shoppers browse six aisles, search as they type, and filter by price and rating.
- Every product has a photo gallery with zoom, and reviews from people who actually bought it.
- The basket works for guests too, and moves into their account when they sign in.
- Customers pay from a built-in wallet, by card, with PayPal, or split one order between the wallet and a card.
- Every order, top-up and refund is listed, with receipts by email.
- The store owner runs everything from an admin panel: products, aisles, orders, payments, refunds, customers and reviews.

## What I built

- **Real payments, in sandbox.** Stripe and PayPal checkout with webhooks, so an order is settled even if the shopper closes the tab before coming back.
- **Money that can't go wrong.** Prices are worked out on the server, amounts are stored as whole cents, and every balance change runs in a locked database transaction with a ledger row, so a balance always matches its history.
- **A full back office.** A Filament admin panel with a sales dashboard, one-click refunds back to the wallet or the card, payment checks with the provider, and review moderation.
- **Honest reviews.** Only customers who bought a product can review it, and a refund removes that review.
- **Tested end to end.** 900+ automated tests, including browser tests that click through the store, plus static analysis at Larastan level 7, all run in CI on every push.
- **A rewrite, not a reskin.** It began in 2018 as plain PHP that trusted prices sent by the browser and changed balances without locking. [What changed in the rewrite](docs/rewrite-2018-2026.md).

## Stack and quality

- **Backend:** Laravel 13, PHP 8.3, MySQL 8.4
- **Front end:** Inertia 3, Vue 3 + TypeScript, Tailwind 4, shadcn-vue
- **Admin:** Filament
- **Payments:** Stripe and PayPal, in sandbox mode
- **Tests:** Pest 4, with browser tests on Playwright
- **Quality:** Pint, Larastan level 7, Oxlint, Oxfmt, vue-tsc and GitHub Actions

## Run it locally

Clone it, install with Composer and npm, then migrate and seed. The [setup guide](docs/setup.md) has every step and the demo logins. There are also guides to [payments in sandbox mode](docs/payments-sandbox.md) and the [admin panel](docs/admin-panel.md). Product photos are credited in [photo credits](docs/photo-credits.md).

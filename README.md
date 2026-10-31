# Cartopia

[![CI](https://github.com/amrcs92/cartopia/actions/workflows/ci.yml/badge.svg)](https://github.com/amrcs92/cartopia/actions/workflows/ci.yml)

Cartopia is a small online store with a virtual wallet. Browse and search a catalog, rate products, fill a basket (as a guest or logged in), and pay for orders from your balance. Top up the wallet whenever you like and review every order and ledger entry.

It started in 2018 as a plain-PHP project and was rebuilt in 2026 as a Laravel showcase: modern idioms, a current front end, real tests and CI.

## Screenshots

| Light                                                 | Dark                                                |
| ----------------------------------------------------- | --------------------------------------------------- |
| ![Home, light](docs/screenshots/home-light.png)       | ![Home, dark](docs/screenshots/home-dark.png)       |
| ![Shop, light](docs/screenshots/shop-light.png)       | ![Shop, dark](docs/screenshots/shop-dark.png)       |
| ![Product, light](docs/screenshots/product-light.png) | ![Product, dark](docs/screenshots/product-dark.png) |
| ![Basket, light](docs/screenshots/basket-light.png)   | ![Basket, dark](docs/screenshots/basket-dark.png)   |
| ![Wallet, light](docs/screenshots/wallet-light.png)   | ![Wallet, dark](docs/screenshots/wallet-dark.png)   |

## Stack

- **Backend:** Laravel 13, PHP 8.3, MySQL 8.4
- **Front end:** Inertia 3, Vue 3 + TypeScript, Tailwind 4, shadcn-vue, Laravel Wayfinder, vite-plus
- **Auth:** Laravel Fortify via the official Vue starter kit (login, registration, password reset, profile, optional two-factor and passkeys)
- **Tests:** Pest 4, including browser tests (pest-plugin-browser with Playwright)
- **Quality:** Pint, Larastan level 7, Oxlint, Oxfmt, vue-tsc
- **CI:** GitHub Actions

## Features

- Product catalog with search, price and rating filters, sorting and pagination
- Aisles (categories) with a filter for each, and a featured carousel on the home, shop and product pages
- Product detail with image gallery and 1-5 star ratings (one rating per user and product)
- Basket for guests (session) and users (database); a guest basket is merged into the account on login or registration
- An added-to-basket panel that confirms each addition and links to the basket
- Checkout paid from the wallet balance, priced entirely on the server
- Order history with price and name snapshots
- Wallet top-ups ($5-$500) with an append-only transaction ledger
- $100 signup bonus for new accounts
- Light and dark themes, with a toggle in the header
- A friendly not-found page that points back to the home page and the shop

## Setup (Laragon, Windows)

Requirements: Laragon with PHP 8.3, MySQL 8.4 and Node 22.

1. Clone the repository into `C:\laragon\www`:
    ```bash
    git clone https://github.com/amrcs92/cartopia.git C:\laragon\www\cartopia
    cd C:\laragon\www\cartopia
    git checkout laravel
    ```
2. Install PHP dependencies: `composer install`
3. Install JS dependencies: `npm install`
4. Create the environment file with `cp .env.example .env`, create an empty MySQL database named `cartopia`, and set `DB_DATABASE=cartopia` (user `root`, empty password on a default Laragon).
5. Generate the app key: `php artisan key:generate`
6. Migrate and seed: `php artisan migrate --seed`
7. Link public storage (product images): `php artisan storage:link`
8. Build the front end: `npm run build`. This needs `php` on your `PATH`, because Wayfinder generates the route helpers at build time.
9. Open <https://cartopia.test> (Laragon creates the virtual host automatically; turn on SSL under Menu → Apache → SSL → Enabled and reload the web server if needed).

For front-end development use `npm run dev` instead of building.

### Demo logins

| Role  | Email                 | Password   |
| ----- | --------------------- | ---------- |
| Demo  | `amrcs1992@gmail.com` | `12345`    |
| Admin | `admin@cartopia.test` | `password` |

The admin account is used by the Filament admin panel, which is planned as the next sub-project.

## Quality checks and tests

```bash
composer lint:check      # Pint
composer analyse         # Larastan level 7
npm run lint             # vp lint (Oxlint)
npm run format:check     # vp fmt --check (Oxfmt)
npm run types            # vue-tsc
npm run build
php artisan test         # or: vendor/bin/pest
```

The browser tests need PHP's `sockets` extension. In Laragon enable it under Menu, PHP, Extensions, `sockets`. Alternatively run the tests with `php -d extension=sockets vendor/bin/pest`. Browser tests also need Playwright's browsers (`npx playwright install chromium`). The rest of the suite runs on in-memory SQLite.

## 2018 → 2026 rewrite

The original app (plain PHP, PDO, jQuery, Bootstrap 4 and a localStorage cart) is still in git history on `master` and `main`. The rewrite fixes its defects:

| 2018 defect                                      | 2026 fix                                                                                                                                                 |
| ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Checkout trusted client-side prices and totals   | The server rebuilds the basket from current database prices; the request carries no price, and order totals are computed in a `PlaceOrder` action        |
| Balance updates had no transaction and no lock   | Every balance change goes through a `Wallet` service: one DB transaction, `lockForUpdate`, and a ledger row, so the balance always equals the ledger sum |
| Orders were not linked to users                  | Orders belong to a user, are protected by an `OrderPolicy`, and snapshot item names and prices                                                           |
| One shared fixed salt with SHA-512 for passwords | Laravel's hasher (bcrypt) with per-password salts, via Fortify                                                                                           |
| No output escaping (XSS) and no CSRF protection  | Vue templates escape by default; Inertia and Laravel send CSRF tokens on every form                                                                      |
| Ratings were unvalidated and could be duplicated | Form request validation (1-5 stars) and a unique `(user_id, product_id)` constraint with `updateOrCreate`                                                |
| N+1 queries on every listing page                | Eager loading (`primaryImage`, `withAvg`, `withCount`) and paginated queries                                                                             |

All money is stored as integer cents and wrapped in a `Money` value object, with no float arithmetic and no money math in the browser.

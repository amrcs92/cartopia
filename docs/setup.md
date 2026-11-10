# Run Cartopia locally

These steps use Laragon on Windows. Any PHP 8.3 + MySQL 8 + Node 22 setup works the same way.

## Setup

Requirements: Laragon with PHP 8.3, MySQL 8.4 and Node 22.

1. Clone the repository into `C:\laragon\www`:
    ```bash
    git clone https://github.com/amrcs92/cartopia.git C:\laragon\www\cartopia
    cd C:\laragon\www\cartopia
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

To fill the store with a month of orders, reviews and a demo basket, as in the screenshots, run `php artisan migrate:fresh --seed --seeder=ShowcaseSeeder`. It wipes the local database first.

## Demo logins

| Role  | Email                 | Password   |
| ----- | --------------------- | ---------- |
| Demo  | `demo@cartopia.test`  | `12345`    |
| Admin | `admin@cartopia.test` | `password` |

The admin account signs in to the [admin panel](admin-panel.md).

## Payments, mail and background work

Card and PayPal payments need sandbox keys, and emails need Mailpit and a queue worker. See [payments in sandbox mode](payments-sandbox.md).

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

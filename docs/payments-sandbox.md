# Payments in sandbox mode

Shoppers can pay with Stripe or PayPal, alone or together with their wallet balance, at checkout and when topping up. Both providers run in sandbox mode only, and `.env` is never committed. After editing `.env` run `php artisan config:clear`.

## Setup

1. **Stripe:** copy the test secret key (`sk_test_…`) from the Stripe dashboard into `STRIPE_SECRET`. For the webhook, run the Stripe CLI:
    ```bash
    stripe listen --forward-to https://cartopia.test/webhooks/stripe --skip-verify
    ```
    Put the `whsec_…` secret it prints into `STRIPE_WEBHOOK_SECRET`. `--skip-verify` is needed because Laragon's certificate is self-signed.
2. **PayPal:** in the PayPal Developer dashboard open a sandbox app (Apps & Credentials) and copy its Client ID and Secret into `PAYPAL_CLIENT_ID` and `PAYPAL_SECRET`.
3. **PayPal webhook:** PayPal must reach your machine, so expose it with ngrok (ideally on your free static domain):
    ```bash
    ngrok http http://localhost:80 --host-header=cartopia.test
    ```
    In the sandbox app add a webhook with the URL `https://<ngrok-host>/webhooks/paypal` and these events: `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED` and `PAYMENT.CAPTURE.DENIED`. Copy the webhook's ID into `PAYPAL_WEBHOOK_ID`. Point ngrok at plain HTTP as above: forwarding to `https://cartopia.test` makes Apache pick its virtual host from the TLS connection rather than the rewritten Host header, so PayPal's webhooks get Apache's 404 instead of reaching Cartopia. Use a sandbox Business account in a country that can receive payments (the United States, say): one in Egypt makes every capture fail with `COMPLIANCE_VIOLATION`.
4. **Mail:** start Mailpit from Laragon and set these lines in `.env`. The inbox is at <http://localhost:8025>.
    ```
    MAIL_MAILER=smtp
    MAIL_HOST=127.0.0.1
    MAIL_PORT=1025
    ```
5. **Background work:** run each in its own terminal. Emails only reach Mailpit while the queue worker runs, and the scheduler expires stale payments.
    ```bash
    php artisan queue:work
    php artisan schedule:work
    ```

## Try it

At <https://cartopia.test>:

- [ ] Stripe card `4242 4242 4242 4242` pays and the order is placed.
- [ ] Stripe card `4000 0025 0000 3155` asks for 3-D Secure, then pays.
- [ ] Stripe card `4000 0000 0000 0002` is declined and Stripe keeps you on its page.
- [ ] PayPal pays with the sandbox Personal (buyer) account, and cancelling on PayPal's page returns you to the basket.
- [ ] A split order: with a wallet balance below the total, put the balance towards the order and pay the rest with Stripe or PayPal.
- [ ] A top-up with Stripe and one with PayPal.
- [ ] Pay with each provider and close the tab before returning. The webhook alone settles it: the order shows as placed and the receipt email arrives.
- [ ] Every email appears in Mailpit and reads well in light and dark.

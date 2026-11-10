import type { Locator } from 'playwright';
import { clickWith, glideScrollTo, typeWith } from '../lib/pointer';
import { settle } from '../lib/settle';
import { beat, type SceneContext } from './scene';

async function waitUntilStill(locator: Locator): Promise<void> {
    let last = '';
    for (let tries = 0; tries < 40; tries++) {
        const box = JSON.stringify(await locator.boundingBox());
        if (box === last && box !== 'null') {
            return;
        }
        last = box;
        await locator.page().waitForTimeout(250);
    }
}

/**
 * Six bottles of olive oil push the basket past Sam's $42.50, so checkout
 * takes the whole balance from the wallet and Stripe's sandbox pays the rest
 * with the published test card.
 */
export async function checkout({ page, rec }: SceneContext): Promise<void> {
    await beat(page, 900);

    const more = page.getByRole('button', { name: /increase|more|\+/i }).first();
    for (let press = 0; press < 5; press++) {
        await clickWith(page, more);
        await page.waitForTimeout(120);
    }
    await beat(page, 500);

    const add = page.getByRole('button', { name: /add to basket/i }).first();
    await rec.mark('add-to-basket', add);
    await clickWith(page, add);
    const view = page.getByRole('link', { name: 'View basket and check out' });
    await view.waitFor();
    await beat(page, 1400);
    await clickWith(page, view);
    await page.waitForURL(/\/cart/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page);

    const payWith = page.locator('fieldset', { has: page.getByText('Pay with') });
    await glideScrollTo(page, payWith, 120);
    await rec.mark('pay-with', payWith);
    await beat(page, 900);
    await clickWith(page, payWith.locator('label', { has: page.locator('input[value="stripe"]') }));
    await beat(page, 900);

    await clickWith(page, page.locator('[data-test="checkout-button"]'));
    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30_000 });
    // Stripe's page keeps connections open, so it never goes network-idle: wait for its form instead.
    await beat(page, 900);
    // Stripe lists several methods; the card fields open once "Card" is chosen.
    // Its "Pay with card" button is a hidden overlay, so the pointer clicks the row it covers.
    const card = page.locator('[data-testid="card-accordion-item"]').first();
    const cardNumber = page.locator('#cardNumber');
    await card.or(cardNumber).first().waitFor({ timeout: 30_000 });
    // Stripe's page shifts while its wallet buttons load: click only once the row holds still.
    for (let attempt = 0; attempt < 3 && !(await cardNumber.isVisible()); attempt++) {
        await waitUntilStill(card);
        await clickWith(page, card);
        await cardNumber.waitFor({ timeout: 4_000 }).catch(() => undefined);
    }
    await page.locator('#cardNumber').waitFor({ timeout: 30_000 });
    await beat(page, 500);

    const email = page.locator('#email');
    if (await email.isVisible()) {
        await typeWith(page, email, 'demo@cartopia.test');
    }
    await typeWith(page, page.locator('#cardNumber'), '4242424242424242');
    await typeWith(page, page.locator('#cardExpiry'), '1234');
    await typeWith(page, page.locator('#cardCvc'), '123');
    await typeWith(page, page.locator('#billingName'), 'Sam Rivera');
    const zip = page.locator('#billingPostalCode');
    if (await zip.isVisible()) {
        await typeWith(page, zip, '10001');
    }
    await beat(page, 500);
    const pay = page.locator('[data-testid="hosted-payment-submit-button"], button[type="submit"]').first();
    await rec.mark('stripe-pay', pay);
    await clickWith(page, pay);

    await page.waitForURL(/cartopia\.test\/orders\/\d+/, { timeout: 60_000, waitUntil: 'commit' });
    const paid = page.locator('[data-test="order-status"]').filter({ hasText: /paid|placed/i });
    try {
        await paid.waitFor({ timeout: 20_000 });
    } catch {
        throw new Error("Stripe did not settle: is 'stripe listen' running and the queue worker started?");
    }
    await settle(page);
    await rec.mark('paid-badge', paid);
    await beat(page, 2000);
}

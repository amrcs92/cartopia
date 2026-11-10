import type { Page } from 'playwright';
import { clickWith, glideScroll, typeWith } from '../lib/pointer';
import { settle } from '../lib/settle';
import { beat, type SceneContext } from './scene';

async function openNav(page: Page, name: string): Promise<void> {
    await clickWith(page, page.locator('.fi-sidebar').getByRole('link', { name }));
    await page.waitForURL(new RegExp(`/admin/${name.toLowerCase()}`), { waitUntil: 'commit' });
    await settle(page);
}

/** The dashboard, a price change, hiding a review, and a one-click refund of one of Sam's wallet orders. */
export async function admin({ page, rec }: SceneContext): Promise<void> {
    await beat(page, 900);
    const chart = page.locator('canvas').first();
    await rec.mark('chart', chart);
    await beat(page, 2200);

    await openNav(page, 'Products');
    await beat(page);
    await clickWith(page, page.getByRole('row', { name: /Bananas/ }).getByRole('link', { name: /edit/i }).first());
    await page.waitForURL(/\/edit/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page);
    const price = page.locator('input[id*="price"]').first();
    await price.fill('');
    await typeWith(page, price, '1.19');
    const save = page.getByRole('button', { name: /save changes/i });
    await rec.mark('product-save', save);
    await clickWith(page, save);
    await page.getByText(/saved/i).first().waitFor();
    await beat(page, 1200);

    await openNav(page, 'Reviews');
    await beat(page);
    const hide = page.getByRole('row', { name: /Daniel Okafor/ }).getByRole('button', { name: 'Hide' }).first();
    await rec.mark('hide-review', hide);
    await clickWith(page, hide);
    const confirmHide = page.getByRole('dialog').getByRole('button', { name: /hide|confirm/i });
    if (await confirmHide.isVisible().catch(() => false)) {
        await clickWith(page, confirmHide.first());
    }
    await beat(page, 1200);

    await openNav(page, 'Orders');
    await beat(page);
    // Order #3: Sam's dark chocolate and mixed nuts, paid from the wallet, with no reviews to lose.
    await clickWith(page, page.getByRole('row', { name: /Sam Rivera/ }).filter({ hasText: '$8.47' }).first());
    await page.waitForURL(/\/admin\/orders\/\d+/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page);
    const refund = page.getByRole('button', { name: /^Refund/ }).first();
    await clickWith(page, refund);
    const dialog = page.getByRole('dialog');
    await dialog.locator('textarea').first().waitFor();
    await beat(page, 600);
    await typeWith(page, dialog.locator('textarea').first(), 'Changed their mind.');
    const confirm = dialog.getByRole('button', { name: /^Refund \$/ });
    await beat(page, 300);
    await rec.mark('refund', confirm);
    await clickWith(page, confirm);
    await page.getByText(/refunded/i).first().waitFor({ timeout: 20_000 });
    await glideScroll(page, 0, 400);
    await beat(page, 1800);
}

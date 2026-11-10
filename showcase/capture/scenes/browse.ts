import { clickWith, glideScroll, hoverWith, typeWith } from '../lib/pointer';
import { settle } from '../lib/settle';
import { beat, type SceneContext } from './scene';

/** Home, then the shop: an aisle filter, live search, and the product gallery's zoom. */
export async function browse({ page, rec }: SceneContext): Promise<void> {
    await beat(page, 1200);
    await glideScroll(page, 520, 1400);
    await beat(page, 900);
    await glideScroll(page, 0, 900);

    await clickWith(page, page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Shop' }));
    await page.waitForURL(/\/products/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page);

    const bakery = page.getByRole('checkbox', { name: /Bakery/ });
    await rec.mark('aisle-chip', bakery);
    await clickWith(page, bakery);
    await page.waitForURL(/aisle/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page, 1200);

    const search = page.getByPlaceholder('Search products').first();
    await rec.mark('search', search);
    await typeWith(page, search, 'honey');
    const suggestion = page.locator('[data-test="search-suggestions"]').getByRole('option', { name: /honey/i }).first();
    await suggestion.waitFor();
    await beat(page, 900);
    await clickWith(page, suggestion);
    await page.waitForURL(/\/products\/honey/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page);

    const gallery = page.locator('[data-test="product-photo"]').first();
    await rec.mark('gallery', gallery);
    await hoverWith(page, gallery, 700);
    const box = await gallery.boundingBox();
    if (box !== null) {
        await page.mouse.move(box.x + box.width * 0.35, box.y + box.height * 0.3, { steps: 25 });
        await page.mouse.move(box.x + box.width * 0.65, box.y + box.height * 0.6, { steps: 35 });
    }
    await beat(page, 1200);
}

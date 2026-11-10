import { clickWith, glideScroll } from '../lib/pointer';
import { settle } from '../lib/settle';
import { beat, type SceneContext } from './scene';

/** The shop at desktop size, held for the side-by-side with the phone. */
export async function responsiveDesktop({ page }: SceneContext): Promise<void> {
    await beat(page, 1200);
    await glideScroll(page, 360, 1800);
    await beat(page, 2500);
}

/** The home page at phone width: a scroll, then a product. */
export async function responsivePhone({ page }: SceneContext): Promise<void> {
    await beat(page, 900);
    await glideScroll(page, 900, 2200);
    await beat(page, 600);
    await clickWith(page, page.locator('a[href*="/products/"]').filter({ has: page.locator('img') }).first());
    await page.waitForURL(/\/products\/[a-z-]+/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page, 2000);
}

/** The shop in light, then the header's theme toggle switches it to dark. */
export async function dark({ page, rec }: SceneContext): Promise<void> {
    await beat(page, 1500);
    const toggle = page.getByRole('button', { name: 'Switch to dark mode' }).first();
    await rec.mark('toggle', toggle);
    await clickWith(page, toggle);
    await page.waitForFunction(() => document.documentElement.classList.contains('dark'));
    await beat(page, 2500);
}

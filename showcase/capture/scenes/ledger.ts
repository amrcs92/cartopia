import { clickWith, glideScrollTo, typeWith } from '../lib/pointer';
import { settle } from '../lib/settle';
import { beat, type SceneContext } from './scene';

/** The wallet and its ledger, the order history, then a review of something Sam bought. */
export async function ledger({ page, rec }: SceneContext): Promise<void> {
    await beat(page, 900);

    const quick = page.getByRole('button', { name: '$25' }).first();
    await rec.mark('top-up', quick);
    await clickWith(page, quick);
    await beat(page, 900);

    const history = page.locator('[data-test="ledger-row"]').first();
    await glideScrollTo(page, history, 220, 1100);
    await rec.mark('ledger', history);
    await beat(page, 1600);

    await clickWith(page, page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Orders' }));
    await page.waitForURL(/\/orders$/, { waitUntil: 'commit' });
    await settle(page);
    await beat(page, 1200);

    // Sam bought honey in his split order and hasn't reviewed it yet: the form sits on the product page.
    await page.goto('/products/honey');
    await settle(page);
    await glideScrollTo(page, page.locator('#reviews'), 140, 1000);
    await beat(page, 600);

    await clickWith(page, page.getByLabel('5 stars').first());
    await beat(page, 400);
    const text = page.getByPlaceholder('What did you think of it?');
    await typeWith(page, text, 'Golden and floral. Lovely in tea.');
    await beat(page, 400);
    const post = page.getByRole('button', { name: 'Post review' });
    await rec.mark('review', post);
    await clickWith(page, post);
    await page.locator('[data-test="my-review"]').waitFor();
    await settle(page);
    await beat(page, 1600);
}

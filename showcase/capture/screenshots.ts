import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import type { Page } from 'playwright';
import { launch, login, type Theme } from './lib/browser';
import { repoRoot, resetShowcase } from './lib/env';
import { settle } from './lib/settle';

const outDir = resolve(repoRoot, 'docs/screenshots');
const work = mkdtempSync(join(tmpdir(), 'showcase-shots-'));
const maxBytes = 400 * 1024;
const paper = '#edf2e4';
const ink = '#1a2b21';

function magick(...args: string[]): string {
    return execFileSync('magick', args, { encoding: 'utf8' }).trim();
}

/** Saves a capture as a JPG at `width` px wide, stepping the quality down until it is 400 KB or less. */
function compress(source: string, name: string, width: number): string {
    const target = join(outDir, `${name}.jpg`);
    for (const quality of [90, 86, 82, 78]) {
        magick(source, '-resize', `${width}x`, '-strip', '-sampling-factor', '4:4:4', '-quality', String(quality), target);
        if (statSync(target).size <= maxBytes) {
            return target;
        }
    }
    throw new Error(`${name} is still over 400 KB at quality 78`);
}

async function shoot(page: Page, name: string, width = 1440): Promise<string> {
    await settle(page);
    const raw = join(work, `${name}.png`);
    await page.screenshot({ path: raw });
    const saved = compress(raw, name, width);
    console.log(`  ${name}.jpg  ${Math.round(statSync(saved).size / 1024)} KB`);

    return raw;
}

async function scrollTo(page: Page, selector: string, block: 'start' | 'center' = 'center'): Promise<void> {
    await page.locator(selector).first().evaluate((element, where) => {
        element.scrollIntoView({ block: where, behavior: 'instant' });
    }, block);
}

async function storeShots(theme: Theme): Promise<Record<string, string>> {
    const { browser, page } = await launch({ width: 1440, height: 900, scale: 2, theme });
    const raws: Record<string, string> = {};
    try {
        await login(page, 'sam');

        await page.goto('/');
        raws.home = await shoot(page, `home-${theme}`);

        await page.goto('/products');
        raws.shop = await shoot(page, `shop-${theme}`);

        await page.goto('/products/olive-oil');
        raws.product = await shoot(page, `product-${theme}`);

        await scrollTo(page, '#reviews', 'start');
        await page.evaluate(() => scrollBy({ top: -40, behavior: 'instant' }));
        raws.reviews = await shoot(page, `reviews-${theme}`);

        await page.goto('/cart');
        raws.basket = await shoot(page, `basket-${theme}`);

        await scrollTo(page, '[data-test="pay-wallet"]');
        raws.checkout = await shoot(page, `checkout-${theme}`);

        await page.goto('/wallet');
        raws.wallet = await shoot(page, `wallet-${theme}`);

        await page.goto('/orders');
        raws.orders = await shoot(page, `orders-${theme}`);

        // The newest order is Sam's split wallet-and-Stripe order.
        await page.locator('[data-test="order-row"] a').first().click();
        await page.waitForURL(/\/orders\/\d+/, { waitUntil: 'commit' });
        raws.receipt = await shoot(page, `receipt-${theme}`);
    } finally {
        await browser.close();
    }

    return raws;
}

async function adminShots(): Promise<void> {
    const { browser, page } = await launch({ width: 1440, height: 900, scale: 2, theme: 'light' });
    try {
        await login(page, 'admin');
        for (const [name, path] of [
            ['admin-dashboard', '/admin'],
            ['admin-orders', '/admin/orders'],
            ['admin-products', '/admin/products'],
            ['admin-payments', '/admin/payments'],
            ['admin-reviews', '/admin/reviews'],
        ]) {
            await page.goto(path);
            await shoot(page, `${name}-light`);
        }
    } finally {
        await browser.close();
    }
}

async function phoneShot(): Promise<string> {
    const { browser, page } = await launch({ width: 390, height: 844, scale: 2, theme: 'light' });
    try {
        await login(page, 'sam');
        await page.goto('/products/olive-oil');

        return await shoot(page, 'product-phone-light', 780);
    } finally {
        await browser.close();
    }
}

/** The desktop product page with the phone view just overlapping its right edge, on the store's paper colour. */
function responsiveShot(desktopRaw: string, phoneRaw: string): void {
    const desktop = join(work, 'desktop-card.png');
    magick(desktopRaw, '-resize', '1060x', '-bordercolor', ink, '-border', '3', desktop);

    const screen = join(work, 'phone-screen.png');
    magick(phoneRaw, '-resize', '300x', screen);
    const height = Number(magick('identify', '-format', '%h', screen));
    const phone = join(work, 'phone-framed.png');
    magick(
        screen,
        '-bordercolor', ink, '-border', '12',
        '(', '+clone', '-alpha', 'transparent', '-fill', 'white',
        '-draw', `roundrectangle 0,0,323,${height + 23},36,36`, ')',
        '-compose', 'DstIn', '-composite',
        phone,
    );

    const composite = join(work, 'responsive.png');
    magick(
        '-size', '1440x760', `xc:${paper}`,
        desktop, '-geometry', '+40+48', '-composite',
        phone, '-geometry', `+1076+${760 - height - 24 - 40}`, '-composite',
        composite,
    );
    const saved = compress(composite, 'responsive', 1440);
    console.log(`  responsive.jpg  ${Math.round(statSync(saved).size / 1024)} KB`);
}

async function main(): Promise<void> {
    mkdirSync(outDir, { recursive: true });
    resetShowcase();

    console.log('Store, light:');
    const light = await storeShots('light');
    console.log('Store, dark:');
    await storeShots('dark');
    console.log('Admin:');
    await adminShots();
    console.log('Phone:');
    const phone = await phoneShot();
    responsiveShot(light.product, phone);

    rmSync(work, { recursive: true, force: true });
}

main().catch((error: unknown) => {
    console.error(error);
    process.exit(1);
});

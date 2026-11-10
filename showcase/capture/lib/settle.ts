import type { Page } from 'playwright';

/**
 * Waits until what is on screen is finished: no requests in flight, fonts
 * loaded, and every image in the viewport decoded. Throws rather than let a
 * half-loaded page into a screenshot or a recording.
 */
export async function settle(page: Page, timeoutMs = 10_000): Promise<void> {
    await page.waitForLoadState('networkidle', { timeout: timeoutMs });
    await page.evaluate(() => document.fonts.ready.then(() => undefined));

    const broken = await page
        .waitForFunction(
            () => {
                const visible = [...document.images].filter((image) => {
                    const box = image.getBoundingClientRect();
                    return box.width > 0 && box.bottom > 0 && box.top < innerHeight && box.right > 0 && box.left < innerWidth;
                });
                return visible.every((image) => image.complete && image.naturalWidth > 0);
            },
            undefined,
            { timeout: timeoutMs },
        )
        .then(() => null)
        .catch(() =>
            page.evaluate(() =>
                [...document.images]
                    .filter((image) => !image.complete || image.naturalWidth === 0)
                    .map((image) => image.currentSrc || image.src),
            ),
        );

    if (broken !== null) {
        throw new Error(`Images did not load on ${page.url()}: ${broken.join(', ')}`);
    }

    await page.waitForTimeout(300);
}

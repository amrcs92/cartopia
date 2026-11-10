import { chromium, type Browser, type BrowserContext, type Page } from 'playwright';
import { baseUrl } from './env';

export type Theme = 'light' | 'dark';

export interface LaunchOptions {
    width: number;
    height: number;
    scale: number;
    theme: Theme;
}

/** A browser on the store with the theme chosen up front, the way a returning visitor has it. */
export async function launch(
    options: LaunchOptions,
): Promise<{ browser: Browser; context: BrowserContext; page: Page }> {
    const browser = await chromium.launch();
    const context = await browser.newContext({
        baseURL: baseUrl,
        viewport: { width: options.width, height: options.height },
        deviceScaleFactor: options.scale,
        colorScheme: options.theme,
        ignoreHTTPSErrors: true,
        reducedMotion: 'no-preference',
    });
    const host = new URL(baseUrl).hostname;
    await context.addCookies([{ name: 'appearance', value: options.theme, domain: host, path: '/' }]);
    // The store keeps the choice in localStorage, Filament in its own "theme" key.
    await context.addInitScript((theme) => {
        localStorage.setItem('appearance', theme);
        localStorage.setItem('theme', theme);
    }, options.theme);

    return { browser, context, page: await context.newPage() };
}

/** The seeded logins from DatabaseSeeder. */
const logins = {
    sam: { email: 'demo@cartopia.test', password: '12345' },
    admin: { email: 'admin@cartopia.test', password: 'password' },
} as const;

export async function login(page: Page, who: keyof typeof logins): Promise<void> {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(logins[who].email);
    await page.locator('input[type="password"]').fill(logins[who].password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { waitUntil: 'commit' });
}

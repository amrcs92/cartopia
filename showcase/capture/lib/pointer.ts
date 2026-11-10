import type { BrowserContext, Locator, Page } from 'playwright';
import { clicked } from './clicks';

// Headless recordings show no cursor, so the scenes draw their own: an ink
// arrow with a cream fill that glides between targets and ripples on a click.
const install = () => {
    const mount = () => {
        if (document.getElementById('showcase-pointer') !== null) {
            return;
        }
        const pointer = document.createElement('div');
        pointer.id = 'showcase-pointer';
        pointer.innerHTML =
            '<svg width="28" height="28" viewBox="0 0 28 28"><path d="M4 2 L4 22 L9.5 17 L13.5 25.5 L17 24 L13 15.8 L20.5 15.5 Z" fill="#fff4d6" stroke="#1a2b21" stroke-width="2" stroke-linejoin="round"/></svg>';
        const last = JSON.parse(sessionStorage.getItem('showcase-pointer') ?? '[960,540]') as [number, number];
        Object.assign(pointer.style, {
            position: 'fixed',
            left: '0',
            top: '0',
            zIndex: '2147483647',
            pointerEvents: 'none',
            transform: `translate(${last[0]}px, ${last[1]}px)`,
            filter: 'drop-shadow(1px 2px 2px rgba(26, 43, 33, 0.35))',
            transition: 'transform 600ms cubic-bezier(0.22, 0.8, 0.24, 1)',
        });
        document.documentElement.appendChild(pointer);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
};

export async function installPointer(context: BrowserContext): Promise<void> {
    await context.addInitScript(install);
}

async function glideTo(page: Page, x: number, y: number, ms: number): Promise<void> {
    await page.evaluate(
        ({ x, y, ms }) => {
            const pointer = document.getElementById('showcase-pointer');
            if (pointer !== null) {
                pointer.style.transition = `transform ${ms}ms cubic-bezier(0.22, 0.8, 0.24, 1)`;
                pointer.style.transform = `translate(${x}px, ${y}px)`;
            }
            sessionStorage.setItem('showcase-pointer', JSON.stringify([x, y]));
        },
        { x, y, ms },
    );
    await page.mouse.move(x, y, { steps: Math.max(1, Math.round(ms / 30)) });
}

async function ripple(page: Page, x: number, y: number): Promise<void> {
    await page.evaluate(
        ({ x, y }) => {
            const ring = document.createElement('div');
            Object.assign(ring.style, {
                position: 'fixed',
                left: `${x - 18}px`,
                top: `${y - 18}px`,
                width: '36px',
                height: '36px',
                borderRadius: '50%',
                border: '3px solid #f5b42e',
                zIndex: '2147483646',
                pointerEvents: 'none',
                transition: 'transform 450ms ease-out, opacity 450ms ease-out',
            });
            document.documentElement.appendChild(ring);
            requestAnimationFrame(() => {
                ring.style.transform = 'scale(1.8)';
                ring.style.opacity = '0';
            });
            setTimeout(() => ring.remove(), 500);
        },
        { x, y },
    );
}

async function centreOf(locator: Locator): Promise<{ x: number; y: number }> {
    await locator.scrollIntoViewIfNeeded();
    const box = await locator.boundingBox();
    if (box === null) {
        throw new Error('The element is not on screen.');
    }

    return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
}

/** Moves the pointer onto the element without clicking. */
export async function hoverWith(page: Page, locator: Locator, ms = 600): Promise<void> {
    const { x, y } = await centreOf(locator);
    await glideTo(page, x, y, ms);
}

/** Glides the pointer to the element, then clicks it with a visible ripple. */
export async function clickWith(page: Page, locator: Locator): Promise<void> {
    const { x, y } = await centreOf(locator);
    await glideTo(page, x, y, 600);
    await page.waitForTimeout(150);
    clicked(page);
    await ripple(page, x, y);
    await page.mouse.click(x, y);
}

export async function typeWith(page: Page, locator: Locator, text: string): Promise<void> {
    await clickWith(page, locator);
    await locator.pressSequentially(text, { delay: 60 });
}

/** Scrolls the window to `y` with an eased glide instead of a jump. */
export async function glideScroll(page: Page, y: number, ms = 900): Promise<void> {
    await page.evaluate(
        ({ y, ms }) =>
            new Promise<void>((done) => {
                const from = scrollY;
                const start = performance.now();
                const step = (now: number) => {
                    const t = Math.min(1, (now - start) / ms);
                    const eased = 1 - Math.pow(1 - t, 3);
                    scrollTo(0, from + (y - from) * eased);
                    if (t < 1) {
                        requestAnimationFrame(step);
                    } else {
                        done();
                    }
                };
                requestAnimationFrame(step);
            }),
        { y, ms },
    );
}

/** Glides the window so the element sits `offset` px below the top. */
export async function glideScrollTo(page: Page, locator: Locator, offset = 160, ms = 900): Promise<void> {
    const top = await locator.evaluate((element) => element.getBoundingClientRect().top + scrollY);
    await glideScroll(page, Math.max(0, top - offset), ms);
}

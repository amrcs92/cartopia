// The edit: which moments of each recording each scene keeps, where the camera zooms,
// and where the clicks sound. Windows are set around the marks and clicks the recorder
// noted, so a fresh recording lines up without retiming anything by hand.
import { planShots, type ShotPlan, type Window } from './shots';
import type { SegmentId, Timeline } from './timeline';

export interface Clip {
    file: string;
    durationMs: number;
    width: number;
    height: number;
    marks: { name: string; atMs: number; box: { x: number; y: number; w: number; h: number } }[];
    clicks: number[];
}

export type Manifest = Record<string, Clip>;

export interface CameraScene {
    file: string;
    width: number;
    plan: ShotPlan;
    focus: { mark: string; frame: number; hold: number; box: Clip['marks'][number]['box'] }[];
    clicks: number[];
}

export interface Edit {
    intro: { titleFrames: number; home: CameraScene };
    browse: CameraScene;
    pay: CameraScene;
    ledger: CameraScene;
    admin: CameraScene;
    screens: { phoneFrames: number; desktop: ShotPlan; phone: ShotPlan; dark: ShotPlan; toggleFrame: number; lightSourceFrame: number };
    close: { stackFrames: number };
    thumbnail: { file: string; sourceFrame: number };
}

const FPS = 30;
const EASE = 18;
/** The title card holds this long before the home page fades in under the intro line. */
const TITLE_FRAMES = 200;
/** The phone and desktop share the screens scene's first 125 frames; dark mode has the rest. */
const PHONE_FRAMES = 125;

function clipOf(manifest: Manifest, id: string): Clip {
    const clip = manifest[id];
    if (!clip) {
        throw new Error(`The manifest has no "${id}" clip: run npm run clips.`);
    }
    return clip;
}

function markOf(clip: Clip, name: string) {
    const mark = clip.marks.find((m) => m.name === name);
    if (!mark) {
        throw new Error(`${clip.file} has no "${name}" mark: record it again with npm run clips.`);
    }
    return mark;
}

const around = (atMs: number, before: number, after: number): Window => ({ startMs: atMs - before, endMs: atMs + after });

function camera(clip: Clip, windows: Window[], frames: number, zooms: [mark: string, hold: number][]): CameraScene {
    const plan = planShots(windows, clip.durationMs, frames, FPS);
    const focus: CameraScene['focus'] = [];
    for (const [name, wanted] of zooms) {
        const mark = markOf(clip, name);
        const frame = plan.frameOf(mark.atMs);
        if (frame === null) {
            continue;
        }
        focus.push({ mark: name, frame: Math.max(EASE, frame), hold: wanted, box: mark.box });
    }
    // A zoom eases out before the next one eases in, and ends before the scene does.
    focus.forEach((f, i) => {
        const next = focus[i + 1];
        const room = (next ? next.frame - EASE : frames) - EASE - f.frame - 1;
        f.hold = Math.max(0, Math.min(f.hold, room));
    });
    const clicks = clip.clicks.map((ms) => plan.frameOf(ms)).filter((f): f is number => f !== null && f < frames);
    return { file: clip.file, width: clip.width, plan, focus, clicks };
}

export function buildEdit(timeline: Timeline, manifest: Manifest): Edit {
    const frames = (id: SegmentId) => timeline.scenes.find((s) => s.id === id)!.frames;

    const browse = clipOf(manifest, 'browse');
    const checkout = clipOf(manifest, 'checkout');
    const ledger = clipOf(manifest, 'ledger');
    const admin = clipOf(manifest, 'admin');
    const desktop = clipOf(manifest, 'responsive-desktop');
    const phone = clipOf(manifest, 'responsive-phone');
    const dark = clipOf(manifest, 'dark');

    // The home page, up to the first click into the shop.
    const home = camera(browse, [{ startMs: 0, endMs: browse.clicks[0] - 400 }], frames('intro') - TITLE_FRAMES, []);

    const add = markOf(checkout, 'add-to-basket').atMs;
    const payWith = markOf(checkout, 'pay-with').atMs;
    const stripePay = markOf(checkout, 'stripe-pay').atMs;
    const paid = markOf(checkout, 'paid-badge').atMs;
    // The clicks between "Pay with" and the Stripe button: the card row, then the card fields.
    const stripeClicks = checkout.clicks.filter((ms) => ms > payWith && ms < stripePay);
    const cardRow = stripeClicks[2] ?? payWith + 8000;

    const toggle = markOf(dark, 'toggle').atMs;
    const darkPlan = planShots([around(toggle, 900, 2600)], dark.durationMs, frames('screens') - PHONE_FRAMES, FPS);

    const like = timeline.captions
        .filter((c) => c.scene === 'close')
        .flatMap((c) => c.words)
        .find((w) => w.text.startsWith('Like'));
    const close = timeline.scenes.find((s) => s.id === 'close')!;

    return {
        intro: { titleFrames: TITLE_FRAMES, home },
        browse: camera(
            browse,
            [
                { startMs: browse.clicks[0] - 500, endMs: markOf(browse, 'search').atMs + 4400 },
                { startMs: markOf(browse, 'gallery').atMs - 1100, endMs: browse.durationMs - 400 },
            ],
            frames('browse'),
            [
                ['aisle-chip', 30],
                ['search', 60],
                ['gallery', 40],
            ],
        ),
        pay: camera(
            checkout,
            [
                { startMs: checkout.clicks[0] - 800, endMs: add + 1300 },
                { startMs: payWith - 3000, endMs: payWith + 3500 },
                { startMs: cardRow - 500, endMs: cardRow + 2400 },
                { startMs: stripePay - 2700, endMs: stripePay + 1700 },
                { startMs: paid - 1300, endMs: checkout.durationMs },
            ],
            frames('pay'),
            [
                ['add-to-basket', 30],
                ['pay-with', 60],
                ['stripe-pay', 30],
                ['paid-badge', 60],
            ],
        ),
        ledger: camera(
            ledger,
            [
                { startMs: 300, endMs: markOf(ledger, 'ledger').atMs + 2700 },
                { startMs: markOf(ledger, 'review').atMs - 4600, endMs: markOf(ledger, 'review').atMs + 3300 },
            ],
            frames('ledger'),
            [
                ['top-up', 20],
                ['ledger', 60],
                ['review', 30],
            ],
        ),
        admin: camera(
            admin,
            [
                { startMs: 0, endMs: 3400 },
                around(admin.clicks[1], 400, 900),
                around(markOf(admin, 'product-save').atMs, 1000, 2000),
                around(markOf(admin, 'hide-review').atMs, 300, 2700),
                around(markOf(admin, 'refund').atMs, 2200, 3500),
            ],
            frames('admin'),
            [
                ['chart', 45],
                ['product-save', 25],
                ['hide-review', 25],
                ['refund', 50],
            ],
        ),
        screens: {
            phoneFrames: PHONE_FRAMES,
            desktop: planShots([{ startMs: 0, endMs: desktop.durationMs }], desktop.durationMs, PHONE_FRAMES, FPS),
            phone: planShots([{ startMs: 0, endMs: 6400 }], phone.durationMs, PHONE_FRAMES, FPS),
            dark: darkPlan,
            toggleFrame: darkPlan.frameOf(toggle)!,
            lightSourceFrame: Math.round(((toggle - 150) * FPS) / 1000),
        },
        close: { stackFrames: like ? like.from - close.from - 4 : Math.round(close.frames / 3) },
        thumbnail: { file: checkout.file, sourceFrame: Math.round(((paid + 600) * FPS) / 1000) },
    };
}

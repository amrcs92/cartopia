import { describe, expect, it } from 'vitest';
import manifest from './fixtures/manifest.json';
import timings from './fixtures/timings.json';
import { buildEdit, type Manifest } from './edit';
import { buildTimeline, type Timings } from './timeline';

const timeline = buildTimeline(timings as Timings);
const edit = buildEdit(timeline, manifest as Manifest);
const scene = (id: string) => timeline.scenes.find((s) => s.id === id)!;

describe('buildEdit', () => {
    it('fills each recorded scene with its footage at 0.85x to 1.6x', () => {
        for (const id of ['browse', 'pay', 'ledger', 'admin'] as const) {
            const shots = edit[id].plan.shots;
            expect(shots.reduce((sum, s) => sum + s.frames, 0)).toBe(scene(id).frames);
            expect(edit[id].plan.rate).toBeGreaterThanOrEqual(0.85);
            expect(edit[id].plan.rate).toBeLessThanOrEqual(1.6);
        }
    });

    it('zooms into every marked moment the cut keeps, inside the scene', () => {
        expect(edit.pay.focus.map((f) => f.mark)).toEqual(['add-to-basket', 'pay-with', 'stripe-pay', 'paid-badge']);
        expect(edit.admin.focus.map((f) => f.mark)).toEqual(['chart', 'product-save', 'hide-review', 'refund']);
        for (const id of ['browse', 'pay', 'ledger', 'admin'] as const) {
            for (const f of edit[id].focus) {
                expect(f.frame).toBeGreaterThanOrEqual(0);
                expect(f.frame + f.hold).toBeLessThan(scene(id).frames);
            }
        }
    });

    it('never starts a zoom before the last one has eased out', () => {
        for (const id of ['browse', 'pay', 'ledger', 'admin'] as const) {
            const focus = edit[id].focus;
            for (let i = 1; i < focus.length; i++) {
                expect(focus[i].frame - 18).toBeGreaterThanOrEqual(focus[i - 1].frame + focus[i - 1].hold + 18);
            }
        }
    });

    it('sounds a click for each click the cut keeps', () => {
        expect(edit.pay.clicks.length).toBeGreaterThanOrEqual(8);
        for (const at of edit.pay.clicks) {
            expect(at).toBeGreaterThanOrEqual(0);
            expect(at).toBeLessThan(scene('pay').frames);
        }
    });

    it('switches from the tech cards to the closing card as the narrator asks "Like what you see?"', () => {
        const like = timeline.captions.flatMap((c) => c.words).find((w) => w.text === 'Like')!;
        expect(scene('close').from + edit.close.stackFrames).toBeLessThanOrEqual(like.from);
        expect(scene('close').from + edit.close.stackFrames).toBeGreaterThan(like.from - 20);
    });

    it('wipes to dark mode inside the screens scene', () => {
        expect(edit.screens.toggleFrame).toBeGreaterThan(0);
        expect(edit.screens.phoneFrames + edit.screens.toggleFrame).toBeLessThan(scene('screens').frames);
    });
});

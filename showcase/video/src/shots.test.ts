import { describe, expect, it } from 'vitest';
import { planShots } from './shots';

describe('planShots', () => {
    it('fills the scene exactly, at one speed for the whole scene', () => {
        const plan = planShots([{ startMs: 0, endMs: 4000 }, { startMs: 10_000, endMs: 16_000 }], 20_000, 250);
        expect(plan.shots.reduce((sum, s) => sum + s.frames, 0)).toBe(250);
        expect(plan.rate).toBeCloseTo(300 / 250, 5);
        expect(plan.shots.map((s) => s.from)).toEqual([0, plan.shots[0].frames]);
        expect(plan.shots.map((s) => s.trimBefore)).toEqual([0, 300]);
    });

    it('merges windows that overlap and clips them to the recording', () => {
        const plan = planShots([{ startMs: 3000, endMs: 9000 }, { startMs: -500, endMs: 4000 }, { startMs: 8000, endMs: 12_000 }], 10_000, 300);
        expect(plan.shots).toHaveLength(1);
        expect(plan.shots[0].trimBefore).toBe(0);
        expect(plan.rate).toBeCloseTo(1, 5);
    });

    it('maps a moment in the recording to its frame in the scene, or null when it was cut', () => {
        const plan = planShots([{ startMs: 0, endMs: 3000 }, { startMs: 6000, endMs: 9000 }], 10_000, 180);
        expect(plan.frameOf(1500)).toBe(45);
        expect(plan.frameOf(7000)).toBe(120);
        expect(plan.frameOf(4500)).toBeNull();
    });

    it('refuses to play footage faster than 1.6x or slower than 0.85x', () => {
        expect(() => planShots([{ startMs: 0, endMs: 20_000 }], 20_000, 300)).toThrow(/1\.6/);
        expect(() => planShots([{ startMs: 0, endMs: 2000 }], 20_000, 300)).toThrow(/0\.85/);
    });
});

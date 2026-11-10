import { describe, expect, it } from 'vitest';
import timings from '../src/fixtures/timings.json';
import { buildTimeline, type Timings } from '../src/timeline';
import { chapters, youtubeText } from './youtube';

const timeline = buildTimeline(timings as Timings, 30);

describe('chapters', () => {
    it('starts at 0:00 and keeps every chapter at least 10 seconds long, as YouTube requires', () => {
        const list = chapters(timeline, 30);
        expect(list[0].at).toBe('0:00');
        expect(list.length).toBeGreaterThanOrEqual(3);
        const starts = [...list.map((c) => c.seconds), timeline.totalFrames / 30];
        for (let i = 0; i < list.length; i++) {
            expect(starts[i + 1] - starts[i]).toBeGreaterThanOrEqual(10);
        }
    });
});

describe('youtubeText', () => {
    it('has the title, the chapters, the repository, the contact line and the tags', () => {
        const text = youtubeText(timeline, 30, ['laravel', 'vue']);
        expect(text).toContain('Cartopia: an online store built with Laravel, Vue and Filament (90-second tour)');
        expect(text).toContain('0:00 ');
        expect(text).toContain('https://github.com/amrcs92/cartopia');
        expect(text).toContain('amrcs1992@gmail.com');
        expect(text).toContain('laravel, vue, portfolio, web developer');
    });
});

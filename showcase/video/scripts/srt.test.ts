import { describe, expect, it } from 'vitest';
import type { Timeline } from '../src/timeline';
import { toSrt } from './srt';

const caption = (from: number, frames: number, text: string): Timeline['captions'][number] => ({
    scene: 'intro',
    from,
    frames,
    words: text.split(' ').map((t, i) => ({ text: t, from: from + i, to: from + i + 1 })),
});

const timeline = (captions: Timeline['captions']): Timeline => ({ totalFrames: 3000, scenes: [], voice: [], captions, musicDuck: [] });

describe('toSrt', () => {
    it('numbers the cues from 1 and times them as HH:MM:SS,mmm', () => {
        const srt = toSrt(timeline([caption(60, 90, 'This is Cartopia,'), caption(2190, 45, 'an online store')]), 30);
        expect(srt).toBe(
            '1\n00:00:02,000 --> 00:00:05,000\nThis is Cartopia,\n\n2\n00:01:13,000 --> 00:01:14,500\nan online store\n',
        );
    });

    it('stretches a cue shorter than 700 ms into the gap after it', () => {
        const srt = toSrt(timeline([caption(30, 6, 'Hi.'), caption(120, 30, 'Next')]), 30);
        expect(srt).toContain('00:00:01,000 --> 00:00:01,700');
    });

    it('never stretches a short cue over the next one', () => {
        const srt = toSrt(timeline([caption(30, 6, 'Hi.'), caption(40, 30, 'Next')]), 30);
        expect(srt).toContain('00:00:01,000 --> 00:00:01,333\nHi.');
    });
});

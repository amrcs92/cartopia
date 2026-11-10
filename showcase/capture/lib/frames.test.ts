import { describe, expect, it } from 'vitest';
import { concatList, toConstantRate } from './frames';

describe('toConstantRate', () => {
    it('holds each frame until the next one, and the last until the end', () => {
        const frames = [
            { tMs: 0, file: 'a.jpg' },
            { tMs: 100, file: 'b.jpg' },
            { tMs: 1000, file: 'c.jpg' },
        ];

        expect(toConstantRate(frames, 1500)).toEqual([
            { file: 'a.jpg', durationS: 0.1 },
            { file: 'b.jpg', durationS: 0.9 },
            { file: 'c.jpg', durationS: 0.5 },
        ]);
    });

    it('drops frames that arrive out of order or at the same instant', () => {
        const frames = [
            { tMs: 0, file: 'a.jpg' },
            { tMs: 0, file: 'dup.jpg' },
            { tMs: 400, file: 'b.jpg' },
            { tMs: 300, file: 'late.jpg' },
        ];

        expect(toConstantRate(frames, 1000).map((frame) => frame.file)).toEqual(['a.jpg', 'b.jpg']);
    });

    it('refuses an empty recording', () => {
        expect(() => toConstantRate([], 1000)).toThrow(/no frames/i);
    });
});

describe('concatList', () => {
    it('writes an ffmpeg concat list that repeats the last frame', () => {
        const list = concatList([
            { file: 'C:\\clips\\a.jpg', durationS: 0.1 },
            { file: 'C:\\clips\\b.jpg', durationS: 0.5 },
        ]);

        expect(list).toBe(
            [
                "file 'C:/clips/a.jpg'",
                'duration 0.100000',
                "file 'C:/clips/b.jpg'",
                'duration 0.500000',
                "file 'C:/clips/b.jpg'",
                '',
            ].join('\n'),
        );
    });
});

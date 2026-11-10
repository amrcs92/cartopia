import type { Timeline } from '../src/timeline';

const MIN_MS = 700;

function stamp(ms: number): string {
    const total = Math.round(ms);
    const pad = (n: number, width = 2) => String(n).padStart(width, '0');
    const h = Math.floor(total / 3_600_000);
    const m = Math.floor((total % 3_600_000) / 60_000);
    const s = Math.floor((total % 60_000) / 1000);
    return `${pad(h)}:${pad(m)}:${pad(s)},${pad(total % 1000, 3)}`;
}

/** The video's subtitles as an SRT file, one cue per on-screen caption. */
export function toSrt(timeline: Timeline, fps: number): string {
    const ms = (frame: number) => (frame * 1000) / fps;
    return timeline.captions
        .map((caption, i) => {
            const start = ms(caption.from);
            const next = timeline.captions[i + 1];
            let end = ms(caption.from + caption.frames);
            if (end - start < MIN_MS) {
                // Too brief to read: stretch into the pause after it, up to where the next cue starts.
                end = Math.min(start + MIN_MS, next ? ms(next.from) : Infinity);
            }
            const text = caption.words.map((w) => w.text).join(' ');
            return `${i + 1}\n${stamp(start)} --> ${stamp(end)}\n${text}\n`;
        })
        .join('\n');
}

import { describe, expect, it } from 'vitest';
import timings from './fixtures/timings.json';
import { buildTimeline, chunkWords, type Timings } from './timeline';

const fps = 30;
const timeline = buildTimeline(timings as Timings, fps);

describe('buildTimeline', () => {
    it('runs about 90 seconds', () => {
        expect(timeline.totalFrames).toBeGreaterThanOrEqual(2655);
        expect(timeline.totalFrames).toBeLessThanOrEqual(2745);
    });

    it('lays the scenes end to end from frame 0, one per narration line', () => {
        expect(timeline.scenes.map((s) => s.id)).toEqual(['intro', 'browse', 'pay', 'ledger', 'admin', 'screens', 'close']);
        expect(timeline.scenes[0].from).toBe(0);
        for (let i = 1; i < timeline.scenes.length; i++) {
            const before = timeline.scenes[i - 1];
            expect(timeline.scenes[i].from).toBe(before.from + before.frames);
        }
        expect(timeline.scenes.reduce((sum, s) => sum + s.frames, 0)).toBe(timeline.totalFrames);
    });

    it('gives the title card at least 4 s and holds the closing card 3 s past the last word', () => {
        const intro = timeline.scenes[0];
        const close = timeline.scenes[6];
        expect(intro.frames).toBeGreaterThanOrEqual(120);
        expect(close.frames).toBeGreaterThanOrEqual(240);
        const lastVoice = timeline.voice[timeline.voice.length - 1];
        expect(timeline.totalFrames - (lastVoice.from + lastVoice.frames)).toBeGreaterThanOrEqual(90);
    });

    it('starts each line inside its own scene, after the scene begins', () => {
        timeline.voice.forEach((line, i) => {
            const scene = timeline.scenes[i];
            expect(line.id).toBe(scene.id);
            expect(line.from).toBeGreaterThanOrEqual(scene.from);
            expect(line.from + line.frames).toBeLessThanOrEqual(scene.from + scene.frames);
        });
    });

    it('keeps every caption inside its scene and every word inside its caption', () => {
        expect(timeline.captions.length).toBeGreaterThan(14);
        for (const caption of timeline.captions) {
            const scene = timeline.scenes.find((s) => s.id === caption.scene)!;
            expect(caption.from).toBeGreaterThanOrEqual(scene.from);
            expect(caption.from + caption.frames).toBeLessThanOrEqual(scene.from + scene.frames);
            for (const word of caption.words) {
                expect(word.from).toBeGreaterThanOrEqual(caption.from);
                expect(word.to).toBeLessThanOrEqual(caption.from + caption.frames);
                expect(word.to).toBeGreaterThanOrEqual(word.from);
            }
        }
    });

    it('never shows two captions at once', () => {
        for (let i = 1; i < timeline.captions.length; i++) {
            const before = timeline.captions[i - 1];
            expect(timeline.captions[i].from).toBeGreaterThanOrEqual(before.from + before.frames);
        }
    });

    it('lowers the music from 6 frames before each line to 9 frames after it', () => {
        expect(timeline.musicDuck).toHaveLength(timeline.voice.length);
        timeline.voice.forEach((line, i) => {
            expect(timeline.musicDuck[i]).toEqual({ from: line.from - 6, to: line.from + line.frames + 9 });
        });
    });
});

describe('chunkWords', () => {
    const word = (text: string) => ({ text, startMs: 0, endMs: 0 });

    it('keeps each chunk to one line of at most 42 characters', () => {
        const words = 'Behind the store sits a full back office and every single thing in it is managed here'.split(' ').map(word);
        for (const chunk of chunkWords(words)) {
            expect(chunk.map((w) => w.text).join(' ').length).toBeLessThanOrEqual(42);
        }
    });

    it('ends a chunk at the end of a sentence', () => {
        const chunks = chunkWords(['It', 'works.', 'Like', 'what', 'you', 'see?'].map(word));
        expect(chunks.map((c) => c.map((w) => w.text).join(' '))).toEqual(['It works.', 'Like what you see?']);
    });

    it('breaks at a comma once the chunk is long enough to stand alone', () => {
        const chunks = chunkWords('Shoppers browse by aisle, search as they type, and see every product'.split(' ').map(word));
        expect(chunks.map((c) => c.map((w) => w.text).join(' '))).toEqual([
            'Shoppers browse by aisle,',
            'search as they type,',
            'and see every product',
        ]);
    });
});

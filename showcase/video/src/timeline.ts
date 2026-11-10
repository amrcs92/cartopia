// Turns the narration's timings into the video's timeline: when each scene starts,
// where each spoken line sits, which subtitle shows when, and when the music dips.

export type SegmentId = 'intro' | 'browse' | 'pay' | 'ledger' | 'admin' | 'screens' | 'close';

export interface Word {
    text: string;
    startMs: number;
    endMs: number;
}

export interface Timings {
    durationMs: number;
    voice: string;
    segments: { id: SegmentId; text: string; startMs: number; endMs: number; words: Word[] }[];
}

export interface Timeline {
    totalFrames: number;
    scenes: { id: SegmentId; from: number; frames: number }[];
    /** Each narration line: where it plays in the video, and which slice of narration.wav it is. */
    voice: { id: SegmentId; from: number; frames: number; startMs: number; endMs: number }[];
    captions: { scene: SegmentId; from: number; frames: number; words: { text: string; from: number; to: number }[] }[];
    musicDuck: { from: number; to: number }[];
}

/** The title card animates for 2 s before the narrator speaks. */
const LEAD = 60;
/** The closing card stays at least 3.5 s after the last word. */
const TAIL = 105;
const TARGET = 2700;
/** Each scene cuts in 300 ms before its line starts. */
const SCENE_LEAD = 9;
/** Breathing room added between lines, at most 0.6 s. */
const MAX_GAP = 18;
const MAX_CHARS = 42;
const MIN_CLAUSE = 18;
const CAPTION_LINGER = 15;

const joined = (words: Word[]) => words.map((w) => w.text).join(' ');

/** Groups words into one-line subtitles, breaking at sentence ends, long clauses or 42 characters. */
export function chunkWords(words: Word[]): Word[][] {
    const chunks: Word[][] = [];
    let current: Word[] = [];
    for (const word of words) {
        if (current.length > 0 && joined([...current, word]).length > MAX_CHARS) {
            chunks.push(current);
            current = [];
        }
        current.push(word);
        if (/[.?!]$/.test(word.text) || (/[,;:]$/.test(word.text) && joined(current).length >= MIN_CLAUSE)) {
            chunks.push(current);
            current = [];
        }
    }
    if (current.length > 0) {
        chunks.push(current);
    }
    return chunks;
}

export function buildTimeline(timings: Timings, fps = 30): Timeline {
    const frames = (ms: number) => Math.round((ms * fps) / 1000);
    const lines = timings.segments;
    const spare = TARGET - TAIL - LEAD - frames(timings.durationMs);
    const gap = Math.max(0, Math.min(MAX_GAP, Math.floor(spare / (lines.length - 1))));
    const place = (ms: number, index: number) => LEAD + frames(ms) + index * gap;

    const voice = lines.map((line, i) => ({
        id: line.id,
        from: place(line.startMs, i),
        frames: frames(line.endMs - line.startMs),
        startMs: line.startMs,
        endMs: line.endMs,
    }));

    const last = voice[voice.length - 1];
    const totalFrames = Math.max(TARGET, last.from + last.frames + TAIL);
    const starts = voice.map((line, i) => (i === 0 ? 0 : line.from - SCENE_LEAD));
    const scenes = lines.map((line, i) => ({
        id: line.id,
        from: starts[i],
        frames: (i + 1 < starts.length ? starts[i + 1] : totalFrames) - starts[i],
    }));

    const captions: Timeline['captions'] = [];
    lines.forEach((line, i) => {
        const scene = scenes[i];
        const sceneEnd = scene.from + scene.frames;
        const chunks = chunkWords(line.words).map((chunk) =>
            chunk.map((w) => ({ text: w.text, from: place(w.startMs, i), to: place(w.endMs, i) })),
        );
        chunks.forEach((words, c) => {
            const from = Math.max(scene.from, words[0].from - 2);
            const next = chunks[c + 1];
            const end = Math.min(sceneEnd, next ? Math.max(from + 1, next[0].from - 2) : words[words.length - 1].to + CAPTION_LINGER);
            captions.push({
                scene: line.id,
                from,
                frames: end - from,
                words: words.map((w) => ({ text: w.text, from: Math.max(from, w.from), to: Math.min(end, Math.max(w.from, w.to)) })),
            });
        });
    });

    const musicDuck = voice.map((line) => ({ from: line.from - 6, to: line.from + line.frames + 9 }));

    return { totalFrames, scenes, voice, captions, musicDuck };
}

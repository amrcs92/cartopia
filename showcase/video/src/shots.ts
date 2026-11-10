// Cuts a long recording down to the moments a scene shows, played at one steady speed
// so the scene ends exactly when the next one starts.

export interface Window {
    startMs: number;
    endMs: number;
}

export interface Shot {
    /** First frame of this shot within the scene. */
    from: number;
    frames: number;
    /** Where the shot starts in the recording, in source frames. */
    trimBefore: number;
}

export interface ShotPlan {
    shots: Shot[];
    rate: number;
    /** The scene frame showing the recording's moment `ms`, or null if that moment was cut. */
    frameOf: (ms: number) => number | null;
}

export const MIN_RATE = 0.85;
export const MAX_RATE = 1.6;

function merge(windows: Window[], durationMs: number): Window[] {
    const clipped = windows
        .map((w) => ({ startMs: Math.max(0, w.startMs), endMs: Math.min(durationMs, w.endMs) }))
        .filter((w) => w.endMs > w.startMs)
        .sort((a, b) => a.startMs - b.startMs);
    const merged: Window[] = [];
    for (const w of clipped) {
        const last = merged[merged.length - 1];
        if (last && w.startMs <= last.endMs) {
            last.endMs = Math.max(last.endMs, w.endMs);
        } else {
            merged.push({ ...w });
        }
    }
    return merged;
}

export function planShots(windows: Window[], durationMs: number, sceneFrames: number, fps = 30): ShotPlan {
    const kept = merge(windows, durationMs);
    const sourceMs = kept.reduce((sum, w) => sum + w.endMs - w.startMs, 0);
    const rate = (sourceMs * fps) / 1000 / sceneFrames;
    if (rate > MAX_RATE) {
        throw new Error(`The shots hold ${(sourceMs / 1000).toFixed(1)} s, which would play at ${rate.toFixed(2)}x, faster than ${MAX_RATE}x: trim them.`);
    }
    if (rate < MIN_RATE) {
        throw new Error(`The shots hold ${(sourceMs / 1000).toFixed(1)} s, which would play at ${rate.toFixed(2)}x, slower than ${MIN_RATE}x: add footage.`);
    }

    const toFrames = (ms: number) => (ms * fps) / 1000 / rate;
    const shots: Shot[] = [];
    let at = 0;
    kept.forEach((w, i) => {
        const frames = i === kept.length - 1 ? sceneFrames - at : Math.round(toFrames(w.endMs - w.startMs));
        shots.push({ from: at, frames, trimBefore: Math.round((w.startMs * fps) / 1000) });
        at += frames;
    });

    const frameOf = (ms: number) => {
        const i = kept.findIndex((w) => ms >= w.startMs && ms <= w.endMs);
        return i === -1 ? null : Math.round(shots[i].from + toFrames(ms - kept[i].startMs));
    };

    return { shots, rate, frameOf };
}

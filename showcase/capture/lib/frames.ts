export interface Frame {
    tMs: number;
    file: string;
}

export interface TimedFrame {
    file: string;
    durationS: number;
}

/**
 * The browser only sends a screencast frame when the page changes, so each
 * frame is held on screen until the next one arrives, and the last until
 * `endMs`. Frames that don't move time forward are dropped.
 */
export function toConstantRate(frames: Frame[], endMs: number): TimedFrame[] {
    if (frames.length === 0) {
        throw new Error('The recording has no frames.');
    }

    const ordered: Frame[] = [];
    for (const frame of frames) {
        const previous = ordered.at(-1);
        if (previous === undefined || frame.tMs > previous.tMs) {
            ordered.push(frame);
        }
    }

    return ordered.map((frame, index) => {
        const until = ordered[index + 1]?.tMs ?? Math.max(endMs, frame.tMs + 1);

        return { file: frame.file, durationS: Math.round(until - frame.tMs) / 1000 };
    });
}

/** An ffmpeg concat-demuxer list. The last file is repeated because ffmpeg ignores the final duration. */
export function concatList(frames: TimedFrame[]): string {
    const path = (file: string) => `file '${file.replaceAll('\\', '/')}'`;
    const lines = frames.flatMap((frame) => [path(frame.file), `duration ${frame.durationS.toFixed(6)}`]);
    lines.push(path(frames[frames.length - 1].file), '');

    return lines.join('\n');
}

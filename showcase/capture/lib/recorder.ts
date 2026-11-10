import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import type { CDPSession, Locator, Page } from 'playwright';
import { repoRoot } from './env';
import { watchClicks } from './clicks';
import { concatList, toConstantRate, type Frame } from './frames';

export interface Mark {
    name: string;
    atMs: number;
    box: { x: number; y: number; w: number; h: number };
}

export interface Clip {
    file: string;
    durationMs: number;
    width: number;
    height: number;
    marks: Mark[];
    /** When the pointer clicked, in ms from the clip's start. */
    clicks: number[];
}

/** Runs Remotion's bundled ffmpeg, so nothing else needs installing. */
export function ffmpeg(args: string[]): void {
    execFileSync('npx', ['remotion', 'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', ...args], {
        cwd: join(repoRoot, 'showcase'),
        stdio: 'inherit',
        shell: true,
    });
}

/**
 * Records a page with Chrome's screencast at the page's full device
 * resolution, then encodes a constant 30 fps H.264 clip. Marks note when
 * something worth zooming into happened, and where it sat on screen.
 */
export class Recorder {
    private session: CDPSession | null = null;
    private readonly frames: Frame[] = [];
    private readonly marks: Mark[] = [];
    private readonly clicks: number[] = [];
    private stopWatching: () => void = () => undefined;
    private readonly dir = mkdtempSync(join(tmpdir(), 'showcase-frames-'));
    private startMs = 0;
    private count = 0;

    constructor(
        private readonly page: Page,
        private readonly width: number,
        private readonly height: number,
    ) {}

    async start(): Promise<void> {
        this.session = await this.page.context().newCDPSession(this.page);
        this.session.on('Page.screencastFrame', (event) => {
            const file = join(this.dir, `${String(this.count++).padStart(6, '0')}.jpg`);
            writeFileSync(file, Buffer.from(event.data, 'base64'));
            const at = (event.metadata.timestamp ?? Date.now() / 1000) * 1000 - this.startMs;
            this.frames.push({ tMs: Math.max(0, at), file });
            void this.session?.send('Page.screencastFrameAck', { sessionId: event.sessionId }).catch(() => undefined);
        });
        this.startMs = Date.now();
        this.stopWatching = watchClicks(this.page, () => this.clicks.push(this.elapsedMs()));
        await this.session.send('Page.startScreencast', {
            format: 'jpeg',
            quality: 92,
            maxWidth: this.width,
            maxHeight: this.height,
            everyNthFrame: 1,
        });
    }

    elapsedMs(): number {
        return Date.now() - this.startMs;
    }

    /** Notes the moment and the element's box, in recorded pixels. */
    async mark(name: string, locator: Locator): Promise<void> {
        const box = await locator.boundingBox();
        if (box === null) {
            throw new Error(`Mark "${name}": the element is not on screen.`);
        }
        const scale = this.width / (this.page.viewportSize()?.width ?? this.width);
        this.marks.push({
            name,
            atMs: this.elapsedMs(),
            box: { x: box.x * scale, y: box.y * scale, w: box.width * scale, h: box.height * scale },
        });
    }

    /** Stops recording and writes `<outDir>/<name>.mp4`. */
    async stop(outDir: string, name: string): Promise<Clip> {
        const endMs = this.elapsedMs();
        this.stopWatching();
        await this.session?.send('Page.stopScreencast');
        await this.session?.detach();

        const list = join(this.dir, 'frames.txt');
        writeFileSync(list, concatList(toConstantRate(this.frames, endMs)));
        mkdirSync(outDir, { recursive: true });
        const file = `${name}.mp4`;
        ffmpeg([
            '-f', 'concat', '-safe', '0', '-i', list,
            '-vf', `scale=${this.width}:${this.height}`,
            '-fps_mode', 'cfr', '-r', '30',
            '-c:v', 'libx264', '-crf', '16', '-preset', 'medium', '-pix_fmt', 'yuv420p',
            // No B-frames and a keyframe every half second, so Remotion can seek to any frame.
            '-bf', '0', '-g', '15', '-movflags', '+faststart',
            join(outDir, file),
        ]);
        rmSync(this.dir, { recursive: true, force: true });

        return { file, durationMs: endMs, width: this.width, height: this.height, marks: this.marks, clicks: this.clicks };
    }
}

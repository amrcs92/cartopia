// The final renders: the 1080p video at -14 LUFS for YouTube, a 720p copy small enough
// for GitHub's README, the thumbnail, the subtitles and the upload text. Once every check
// passes, the five files are copied to Downloads (or SHOWCASE_DELIVER_DIR).
import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { repoRoot } from '../../capture/lib/env';
import { buildTimeline, type Timings } from '../src/timeline';
import { toSrt } from './srt';
import { youtubeText } from './youtube';

const FPS = 30;
const LOUDNESS = 'I=-14:TP=-1.5:LRA=11';
const MAX_720P_BYTES = 10_485_760;
const TOPICS = ['ecommerce', 'filament', 'inertiajs', 'laravel', 'paypal', 'pest', 'stripe', 'tailwindcss', 'typescript', 'vue'];

const showcase = join(repoRoot, 'showcase');
const out = join(showcase, 'out');
const files = {
    master: join(out, 'walkthrough-master.mp4'),
    hd: join(out, 'cartopia-walkthrough-1080p.mp4'),
    small: join(out, 'cartopia-walkthrough-720p.mp4'),
    thumbnail: join(out, 'cartopia-thumbnail.png'),
    srt: join(out, 'cartopia-walkthrough.srt'),
    youtube: join(out, 'youtube.md'),
};

/** Runs a Remotion CLI command from showcase/ and returns what it printed to stderr. */
function remotion(args: string[], quiet = false): string {
    const result = spawnSync('npx', ['remotion', ...args], {
        cwd: showcase,
        shell: true,
        encoding: 'utf8',
        maxBuffer: 64 * 1024 * 1024,
        stdio: quiet ? 'pipe' : ['ignore', 'inherit', 'pipe'],
    });
    if (result.status !== 0) {
        throw new Error(`npx remotion ${args.join(' ')} failed:\n${result.stderr}`);
    }
    return result.stderr ?? '';
}

function step(text: string): void {
    console.log(`\n${text}`);
}

interface Loudness {
    input_i: string;
    input_tp: string;
    input_lra: string;
    input_thresh: string;
    target_offset: string;
}

/** Measures a file's loudness with ffmpeg's loudnorm, which prints its numbers as JSON. */
function measure(file: string): Loudness {
    const log = remotion(['ffmpeg', '-hide_banner', '-nostats', '-i', file, '-vn', '-af', `loudnorm=${LOUDNESS}:print_format=json`, '-c:a', 'pcm_s16le', '-f', 'null', '-'], true);
    const json = log.slice(log.lastIndexOf('{'), log.lastIndexOf('}') + 1);
    return JSON.parse(json) as Loudness;
}

function probe(file: string): { width: number; height: number; fps: string; seconds: number } {
    const result = spawnSync(
        'npx',
        ['remotion', 'ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height,r_frame_rate:format=duration', '-of', 'json', file],
        { cwd: showcase, shell: true, encoding: 'utf8' },
    );
    const info = JSON.parse(result.stdout) as { streams: { width: number; height: number; r_frame_rate: string }[]; format: { duration: string } };
    const stream = info.streams[0];
    return { width: stream.width, height: stream.height, fps: stream.r_frame_rate, seconds: Number(info.format.duration) };
}

function check(ok: boolean, message: string): void {
    if (!ok) {
        console.error(`\n${message}`);
        process.exit(1);
    }
    console.log(`  ok: ${message}`);
}

function main(): void {
    mkdirSync(out, { recursive: true });
    const timings = JSON.parse(readFileSync(join(showcase, 'video/public/voice/timings.json'), 'utf8')) as Timings;
    const timeline = buildTimeline(timings, FPS);

    // --reuse skips the slow render when an earlier run already made the master.
    if (process.argv.includes('--reuse') && existsSync(files.master)) {
        step('1/6  Reusing the rendered master.');
    } else {
        step('1/6  Rendering the walkthrough at 1080p...');
        remotion(['render', 'Walkthrough', files.master, '--crf', '18', '--audio-bitrate', '192k', '--log', 'error']);
    }

    step('2/6  Setting the loudness to -14 LUFS...');
    const m = measure(files.master);
    remotion([
        'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-i', files.master,
        '-af', `loudnorm=${LOUDNESS}:measured_I=${m.input_i}:measured_TP=${m.input_tp}:measured_LRA=${m.input_lra}:measured_thresh=${m.input_thresh}:offset=${m.target_offset}:linear=true`,
        '-ar', '48000', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', files.hd,
    ]);

    step('3/6  Encoding the 720p copy for the README...');
    const passlog = join(out, 'x264-720p');
    const scale = ['-vf', 'scale=1280:720:flags=lanczos', '-c:v', 'libx264', '-preset', 'slow', '-b:v', '780k', '-pix_fmt', 'yuv420p', '-passlogfile', passlog];
    remotion(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-i', files.hd, ...scale, '-pass', '1', '-an', '-f', 'null', '-']);
    remotion(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-i', files.hd, ...scale, '-pass', '2', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', files.small]);

    step('4/6  Rendering the thumbnail...');
    remotion(['still', 'Thumbnail', files.thumbnail, '--log', 'error']);

    step('5/6  Writing the subtitles and the YouTube text...');
    writeFileSync(files.srt, toSrt(timeline, FPS));
    writeFileSync(files.youtube, youtubeText(timeline, FPS, TOPICS));

    step('6/6  Checking the results...');
    const hd = probe(files.hd);
    check(hd.width === 1920 && hd.height === 1080 && hd.fps === '30/1', `1080p is ${hd.width} x ${hd.height} at ${hd.fps} fps`);
    check(hd.seconds >= 88.5 && hd.seconds <= 91.5, `1080p runs ${hd.seconds.toFixed(2)} s`);
    const loud = Number(measure(files.hd).input_i);
    check(Math.abs(loud + 14) <= 1, `1080p loudness is ${loud.toFixed(1)} LUFS`);
    const size = statSync(files.small).size;
    check(size <= MAX_720P_BYTES, `720p is ${(size / 1024 / 1024).toFixed(2)} MB, within GitHub's 10 MB`);
    const thumb = probe(files.thumbnail);
    check(thumb.width === 1280 && thumb.height === 720, `thumbnail is ${thumb.width} x ${thumb.height}`);

    rmSync(files.master, { force: true });
    rmSync(`${passlog}-0.log`, { force: true });
    rmSync(`${passlog}-0.log.mbtree`, { force: true });

    const deliver = process.env.SHOWCASE_DELIVER_DIR ?? join(homedir(), 'Downloads');
    mkdirSync(deliver, { recursive: true });
    console.log(`\nCopied to ${deliver}:`);
    for (const file of [files.hd, files.small, files.thumbnail, files.srt, files.youtube]) {
        const target = join(deliver, file.split(/[\\/]/).pop()!);
        copyFileSync(file, target);
        console.log(`  ${target}`);
    }
}

main();

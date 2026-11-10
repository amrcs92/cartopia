import type { SegmentId, Timeline } from '../src/timeline';

const TITLE = 'Cartopia: an online store built with Laravel, Vue and Filament (90-second tour)';
const REPO = 'https://github.com/amrcs92/cartopia';
const MIN_CHAPTER_S = 10;

const NAMES: Record<SegmentId, string> = {
    intro: 'Cartopia',
    browse: 'Browsing and search',
    pay: 'Checkout: wallet, card and PayPal',
    ledger: 'Wallet ledger and reviews',
    admin: 'The back office',
    screens: 'Any screen, light or dark',
    close: 'Built with, and working together',
};

const clock = (seconds: number) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

/**
 * One chapter per scene. YouTube ignores chapters when any is under 10 seconds, so a
 * short scene joins the chapter after it.
 */
export function chapters(timeline: Timeline, fps: number): { at: string; seconds: number; name: string }[] {
    const end = timeline.totalFrames / fps;
    const list: { seconds: number; names: string[] }[] = [];
    let carried: { seconds: number; names: string[] } | null = null;
    timeline.scenes.forEach((scene, i) => {
        const start = scene.from / fps;
        const next = i + 1 < timeline.scenes.length ? timeline.scenes[i + 1].from / fps : end;
        const chapter = carried ?? { seconds: start, names: [] };
        chapter.names.push(NAMES[scene.id]);
        carried = next - chapter.seconds < MIN_CHAPTER_S ? chapter : null;
        if (carried === null) {
            list.push(chapter);
        }
    });
    if (carried !== null) {
        // A short last scene joins the chapter before it instead.
        list[list.length - 1].names.push(...(carried as { names: string[] }).names);
    }
    return list.map((c) => ({ at: clock(c.seconds), seconds: c.seconds, name: c.names.join(' / ') }));
}

/** The title, description and tags to paste into YouTube's upload form. */
export function youtubeText(timeline: Timeline, fps: number, topics: string[]): string {
    const chapterLines = chapters(timeline, fps).map((c) => `${c.at} ${c.name}`);
    return [
        '# YouTube upload',
        '',
        '## Title',
        '',
        TITLE,
        '',
        '## Description',
        '',
        'Cartopia is an online grocery store I rebuilt from the ground up in Laravel 13, Vue 3 and Filament. This tour shows the shop, a split wallet and Stripe checkout, the wallet ledger, verified-buyer reviews and the admin back office.',
        '',
        ...chapterLines,
        '',
        `Source code: ${REPO}`,
        '',
        'Work with me: I build online stores and custom web applications with Laravel and Vue, from the first idea to launch. If you have a project in mind, email me at amrcs1992@gmail.com.',
        '',
        '## Tags',
        '',
        [...topics, 'portfolio', 'web developer'].join(', '),
        '',
        '## Files',
        '',
        '- Video: cartopia-walkthrough-1080p.mp4',
        '- Thumbnail: cartopia-thumbnail.png',
        '- Subtitles: cartopia-walkthrough.srt (English)',
        '',
    ].join('\n');
}

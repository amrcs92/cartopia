import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { launch, login } from './lib/browser';
import { repoRoot, resetShowcase } from './lib/env';
import { installPointer } from './lib/pointer';
import { Recorder, type Clip } from './lib/recorder';
import { settle } from './lib/settle';
import { admin } from './scenes/admin';
import { browse } from './scenes/browse';
import { checkout } from './scenes/checkout';
import { ledger } from './scenes/ledger';
import { dark, responsiveDesktop, responsivePhone } from './scenes/responsive';
import type { SceneContext } from './scenes/scene';

export type SceneId = 'browse' | 'checkout' | 'ledger' | 'admin' | 'responsive-desktop' | 'responsive-phone' | 'dark';

interface Scene {
    id: SceneId;
    who: 'sam' | 'admin' | null;
    start: string;
    phone?: boolean;
    run: (context: SceneContext) => Promise<void>;
}

// The order matters: the ledger scene shows Sam's $42.50 before checkout spends it,
// and admin refunds one of his orders.
const scenes: Scene[] = [
    { id: 'browse', who: null, start: '/', run: browse },
    { id: 'ledger', who: 'sam', start: '/wallet', run: ledger },
    { id: 'checkout', who: 'sam', start: '/products/olive-oil', run: checkout },
    { id: 'admin', who: 'admin', start: '/admin', run: admin },
    { id: 'responsive-desktop', who: null, start: '/products', run: responsiveDesktop },
    { id: 'responsive-phone', who: null, start: '/', phone: true, run: responsivePhone },
    { id: 'dark', who: null, start: '/products', run: dark },
];

const outDir = resolve(repoRoot, 'showcase/video/public/clips');

async function record(scene: Scene): Promise<Clip> {
    const size = scene.phone ? { width: 390, height: 844, scale: 3 } : { width: 1920, height: 1080, scale: 1.5 };
    const { browser, context, page } = await launch({ ...size, theme: 'light' });
    try {
        if (scene.who !== null) {
            await login(page, scene.who);
        }
        await installPointer(context);
        await page.goto(scene.start);
        await settle(page);

        const rec = new Recorder(page, size.width * size.scale, size.height * size.scale);
        await rec.start();
        try {
            await scene.run({ page, rec });
        } catch (error) {
            const shot = resolve(repoRoot, `showcase/out/failed-${scene.id}.png`);
            await page.screenshot({ path: shot }).catch(() => undefined);
            writeFileSync(shot.replace(/\.png$/, '.html'), await page.content().catch(() => ''));
            console.error(`  ${scene.id} failed on ${page.url()}; the page is in ${shot}`);
            throw error;
        }

        return await rec.stop(outDir, scene.id);
    } finally {
        await browser.close();
    }
}

async function main(): Promise<void> {
    const only = process.argv.slice(2);
    if (only.length === 0) {
        resetShowcase();
    }

    // A full run starts a fresh manifest; re-recording some scenes keeps the others' entries.
    const path = join(outDir, 'manifest.json');
    const manifest: Partial<Record<SceneId, Clip>> =
        only.length > 0 && existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : {};

    for (const scene of scenes.filter((s) => only.length === 0 || only.includes(s.id))) {
        console.log(`Recording ${scene.id}...`);
        const clip = await record(scene);
        manifest[scene.id] = clip;
        mkdirSync(outDir, { recursive: true });
        writeFileSync(path, JSON.stringify(manifest, null, 2));
        console.log(`  ${clip.file}  ${(clip.durationMs / 1000).toFixed(1)} s  ${clip.marks.length} marks`);
    }
}

main().catch((error: unknown) => {
    console.error(error);
    process.exit(1);
});

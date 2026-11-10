import type { Page } from 'playwright';
import type { Recorder } from '../lib/recorder';

export interface SceneContext {
    page: Page;
    rec: Recorder;
}

/** A beat after each visible change, so the edit has room to breathe. */
export const beat = (page: Page, ms = 700): Promise<void> => page.waitForTimeout(ms);

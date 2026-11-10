import { describe, expect, it } from 'vitest';
import { clicked, watchClicks } from './clicks';

describe('click watching', () => {
    it('tells the watcher of the page that was clicked, and no other', () => {
        const page = {};
        const other = {};
        const heard: string[] = [];
        watchClicks(page, () => heard.push('page'));
        watchClicks(other, () => heard.push('other'));

        clicked(page);

        expect(heard).toEqual(['page']);
    });

    it('stops telling a watcher once it unsubscribes', () => {
        const page = {};
        let count = 0;
        const stop = watchClicks(page, () => count++);
        clicked(page);
        stop();
        clicked(page);

        expect(count).toBe(1);
    });
});

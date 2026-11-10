// The pointer says when it clicks, and the recorder listens, so each clip knows its click times.
const watchers = new Map<object, Set<() => void>>();

/** Calls `fn` whenever the pointer clicks on `page`; returns a function that stops it. */
export function watchClicks(page: object, fn: () => void): () => void {
    const set = watchers.get(page) ?? new Set();
    set.add(fn);
    watchers.set(page, set);
    return () => {
        set.delete(fn);
    };
}

/** The pointer calls this as it clicks on `page`. */
export function clicked(page: object): void {
    for (const fn of watchers.get(page) ?? []) {
        fn();
    }
}

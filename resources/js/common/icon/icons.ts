// Line icons on a 24px grid (design-assets.md, "Icon paths"). Each icon is a
// list of path `d` strings plus optional circles as [cx, cy, r].
export type IconName =
    | 'basket-add'
    | 'basket'
    | 'search'
    | 'menu'
    | 'close'
    | 'moon'
    | 'sun'
    | 'remove'
    | 'filters'
    | 'pickup'
    | 'delivery'
    | 'chevron-left'
    | 'chevron-right'
    | 'check';

type IconShape = {
    paths: string[];
    circles?: [number, number, number][];
};

const basket = 'M4.5 9.5h15l-1.5 9.6a2 2 0 0 1-2 1.7H8a2 2 0 0 1-2-1.7z';
const handle = 'M8.5 9.5l3.5-6 3.5 6';

export const icons: Record<IconName, IconShape> = {
    'basket-add': { paths: [basket, handle, 'M12 12.5v5.2', 'M9.4 15.1h5.2'] },
    basket: { paths: [basket, handle] },
    search: { paths: ['M20 20l-4-4'], circles: [[11, 11, 7]] },
    menu: { paths: ['M4 7h16M4 12h16M4 17h16'] },
    close: { paths: ['M6 6l12 12M18 6L6 18'] },
    moon: { paths: ['M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z'] },
    sun: {
        paths: [
            'M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8',
        ],
        circles: [[12, 12, 4.5]],
    },
    remove: {
        paths: [
            'M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3',
        ],
    },
    filters: { paths: ['M4 6h16M7 12h10M10 18h4'] },
    pickup: { paths: ['M3 10l9-6 9 6v10H3zM9 20v-6h6v6'] },
    delivery: {
        paths: ['M2 7h11v10H2zM13 10h4l4 4v3h-8z'],
        circles: [
            [6, 18, 1.5],
            [17, 18, 1.5],
        ],
    },
    'chevron-left': { paths: ['M15 5l-7 7 7 7'] },
    'chevron-right': { paths: ['M9 5l7 7-7 7'] },
    check: { paths: ['M7 12.5l3.2 3L17 9'] },
};

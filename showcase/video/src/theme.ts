import { loadFont } from '@remotion/fonts';
import figtree500 from '@fontsource/figtree/files/figtree-latin-500-normal.woff2';
import figtree600 from '@fontsource/figtree/files/figtree-latin-600-normal.woff2';
import figtree800 from '@fontsource/figtree/files/figtree-latin-800-normal.woff2';
import youngSerif from '@fontsource/young-serif/files/young-serif-latin-400-normal.woff2';

// The store's light theme, from :root in resources/css/app.css.
export const colors = {
    paper: '#edf2e4',
    ink: '#1a2b21',
    green: '#1f5a3d',
    greenDeep: '#1f5a3d',
    marigold: '#f5b42e',
    cream: '#fff4d6',
    stripe: '#ffffff',
} as const;

export const serif = '"Young Serif", Georgia, serif';
export const sans = 'Figtree, "Segoe UI", sans-serif';

/** The store's cards: a hard 5 px shadow, no blur. */
export const hardShadow = `5px 5px 0 ${colors.green}`;

export const fontsReady = Promise.all([
    loadFont({ family: 'Young Serif', url: youngSerif, weight: '400' }),
    loadFont({ family: 'Figtree', url: figtree500, weight: '500' }),
    loadFont({ family: 'Figtree', url: figtree600, weight: '600' }),
    loadFont({ family: 'Figtree', url: figtree800, weight: '800' }),
]);

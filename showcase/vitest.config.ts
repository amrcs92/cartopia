import { defineConfig } from 'vitest/config';

// Its own config, so vitest doesn't load the store's vite.config.ts from the parent folder.
export default defineConfig({
    test: {
        include: ['capture/**/*.test.ts', 'video/**/*.test.ts'],
    },
});

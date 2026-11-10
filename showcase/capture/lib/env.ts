import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
export const baseUrl = process.env.SHOWCASE_URL ?? 'https://cartopia.test';

function readEnv(envFile: string): Record<string, string> {
    const values: Record<string, string> = {};
    for (const line of readFileSync(envFile, 'utf8').split(/\r?\n/)) {
        const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
        if (match) {
            values[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
        }
    }

    return values;
}

/**
 * The showcase scripts wipe the database they run against, so they only run
 * against a local app and never against the test database.
 */
export function assertLocalDatabase(envFile: string): { database: string } {
    const env = readEnv(envFile);

    if (env.APP_ENV !== 'local') {
        throw new Error(`Refusing to reset the database: ${envFile} needs APP_ENV=local, not "${env.APP_ENV ?? ''}".`);
    }
    if (!env.DB_DATABASE) {
        throw new Error(`Refusing to reset the database: ${envFile} has no DB_DATABASE.`);
    }
    if (env.DB_DATABASE.endsWith('_testing')) {
        throw new Error(`Refusing to reset the database: ${env.DB_DATABASE} is the test database.`);
    }

    return { database: env.DB_DATABASE };
}

/** Resets the local database to the ShowcaseSeeder state. */
export function resetShowcase(): void {
    const { database } = assertLocalDatabase(resolve(repoRoot, '.env'));
    console.log(`Resetting the local "${database}" database to the showcase state...`);
    execFileSync(
        process.env.PHP_BIN ?? 'php',
        ['artisan', 'migrate:fresh', '--seed', '--seeder=ShowcaseSeeder', '--force'],
        { cwd: repoRoot, stdio: 'inherit' },
    );
}

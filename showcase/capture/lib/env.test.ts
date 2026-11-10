import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { assertLocalDatabase } from './env';

function envFile(contents: string): string {
    const path = join(mkdtempSync(join(tmpdir(), 'showcase-env-')), '.env');
    writeFileSync(path, contents);

    return path;
}

describe('assertLocalDatabase', () => {
    it('refuses an app that is not running locally', () => {
        expect(() =>
            assertLocalDatabase(envFile('APP_ENV=production\nDB_DATABASE=cartopia\n')),
        ).toThrow(/APP_ENV=local/);
    });

    it('refuses the test database', () => {
        expect(() =>
            assertLocalDatabase(envFile('APP_ENV=local\nDB_DATABASE=cartopia_testing\n')),
        ).toThrow(/cartopia_testing/);
    });

    it('refuses a missing database name', () => {
        expect(() => assertLocalDatabase(envFile('APP_ENV=local\nDB_DATABASE=\n'))).toThrow(
            /DB_DATABASE/,
        );
    });

    it('accepts the local dev database, quoted or not', () => {
        expect(assertLocalDatabase(envFile('APP_ENV=local\nDB_DATABASE=cartopia\n'))).toEqual({
            database: 'cartopia',
        });
        expect(
            assertLocalDatabase(envFile('# comment\nAPP_ENV="local"\nDB_DATABASE="cartopia"\n')),
        ).toEqual({ database: 'cartopia' });
    });
});

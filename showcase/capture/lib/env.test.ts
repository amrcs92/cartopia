import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { assertLocalDatabase, assertSafeConnection } from './env';

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

// What Laravel itself resolves, after cached config and environment variables have had their say.
describe('assertSafeConnection', () => {
    const local = { environment: 'local', driver: 'mysql', host: '127.0.0.1', database: 'cartopia' };

    it('accepts the local database on this machine', () => {
        expect(() => assertSafeConnection(local)).not.toThrow();
        expect(() => assertSafeConnection({ ...local, host: 'localhost' })).not.toThrow();
    });

    it('refuses when Laravel resolves an environment other than local', () => {
        expect(() => assertSafeConnection({ ...local, environment: 'production' })).toThrow(/production/);
    });

    it('refuses a database on another host', () => {
        expect(() => assertSafeConnection({ ...local, host: 'db.example.com' })).toThrow(/db\.example\.com/);
    });

    it('refuses the test database even when .env names another', () => {
        expect(() => assertSafeConnection({ ...local, database: 'cartopia_testing' })).toThrow(/cartopia_testing/);
    });
});

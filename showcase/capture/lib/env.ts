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

export interface Connection {
    environment: string;
    driver: string;
    host: string;
    database: string;
}

const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1'];

/**
 * Checks the connection Laravel actually resolves, which cached config and shell
 * environment variables can change without touching .env.
 */
export function assertSafeConnection(connection: Connection): void {
    if (connection.environment !== 'local') {
        throw new Error(`Refusing to reset the database: Laravel is running as "${connection.environment}", not local.`);
    }
    if (connection.driver !== 'sqlite' && !LOCAL_HOSTS.includes(connection.host)) {
        throw new Error(`Refusing to reset the database: it is on ${connection.host}, not this machine.`);
    }
    if (!connection.database || connection.database.endsWith('_testing')) {
        throw new Error(`Refusing to reset the database: "${connection.database}" is the test database or has no name.`);
    }
}

/** Boots the app and asks it which database it would use. */
export function resolvedConnection(php: string): Connection {
    const script = [
        "require 'vendor/autoload.php';",
        "$app = require 'bootstrap/app.php';",
        '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
        "$c = config('database.connections.' . config('database.default'));",
        "echo json_encode(['environment' => app()->environment(), 'driver' => $c['driver'] ?? '', 'host' => (string) ($c['host'] ?? ''), 'database' => (string) ($c['database'] ?? '')]);",
    ].join(' ');
    const output = execFileSync(php, ['-r', script], { cwd: repoRoot, encoding: 'utf8' });
    return JSON.parse(output.slice(output.indexOf('{'))) as Connection;
}

/** Resets the local database to the ShowcaseSeeder state. */
export function resetShowcase(): void {
    const php = process.env.PHP_BIN ?? 'php';
    assertLocalDatabase(resolve(repoRoot, '.env'));
    const connection = resolvedConnection(php);
    assertSafeConnection(connection);
    console.log(`Resetting the local "${connection.database}" database to the showcase state...`);
    // No --force: if Laravel ever thinks it's in production, its own confirmation stops the reset.
    execFileSync(php, ['artisan', 'migrate:fresh', '--seed', '--seeder=ShowcaseSeeder', '--no-interaction'], {
        cwd: repoRoot,
        stdio: 'inherit',
    });
}

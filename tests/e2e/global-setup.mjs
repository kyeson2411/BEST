// Resets the local demo database before the browser tests and records the ids they need.
// This erases everything in the database named in .env, so it refuses to run unless APP_ENV=local.
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

export const php = process.env.SIBOL_PHP || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');

export default async function globalSetup() {
  const env = readFileSync('.env', 'utf8');
  if (!/^APP_ENV\s*=\s*local\s*$/m.test(env)) {
    throw new Error('Browser tests reset the database, so they only run with APP_ENV=local in .env.');
  }
  if (process.env.SIBOL_SKIP_RESET !== '1') {
    execFileSync(php, ['bin/sibol.php', 'db:reset'], { stdio: 'inherit' });
  }
  const fixtures = execFileSync(php, ['tests/e2e/fixtures.php'], { encoding: 'utf8' });
  mkdirSync('tests/e2e/.auth', { recursive: true });
  writeFileSync('tests/e2e/.auth/fixtures.json', fixtures);
}

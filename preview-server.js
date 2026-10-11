/**
 * Development launcher for the REAL Laravel application.
 * No separate users, passwords, reservations or simulated authentication.
 */
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
if (process.env.APP_ENV === 'production') throw new Error('Use the documented production web server.');
const port = Number(process.env.PORT || 3000);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('Invalid PORT.');
console.log('Starting Laravel on http://127.0.0.1:' + port + ' (development only).');
const child = spawn(process.env.PHP_BINARY || 'php', ['artisan', 'serve', '--host=127.0.0.1', '--port=' + port], {
    cwd: fileURLToPath(new URL('.', import.meta.url)), stdio: 'inherit', env: process.env
});
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));

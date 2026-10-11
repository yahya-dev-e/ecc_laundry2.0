# School-network private test deployment (Laragon / Windows Server)

Prepared branch: `codex/private-school-test-2026-10-11`. This guide has not been executed on the server.
Keep `C:\laragon\www\ecc_laundryV2`, its Git branch, database and Apache virtual host unchanged.
The screenshot shows server IP `172.28.2.10`; confirm this is still correct.

## 1. Separate checkout and dependencies

In Laragon Terminal, use a folder outside its automatically published www directory:

```bat
git clone --branch codex/private-school-test-2026-10-11 --single-branch https://github.com/yahya-dev-e/ecc_laundry2.0.git C:\laragon\private\laundry-test
cd /d C:\laragon\private\laundry-test
composer install --no-dev --prefer-dist --optimize-autoloader
copy .env.example .env
```

Build assets using Node 22 or newer supported by Vite (`npm ci` then `npm run build`). If Node is unavailable on this older server, build the exact branch on your own computer and copy only `public/build` into this checkout.

## 2. Fresh database, never a production clone

In MySQL administration, create `ecc_laundry_private_test` and a new password-protected MySQL account with privileges ONLY on that database. Do not use the production account or copy production .env, users, bookings, queues or sessions.

Edit the NEW .env with those credentials and these values:

```dotenv
APP_NAME="ECC Laundry — PRIVATE TEST"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://laundry.fecc.ma:8443
APP_TIMEZONE=Africa/Casablanca
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecc_laundry_private_test
DB_USERNAME=YOUR_NEW_TEST_DB_USER
DB_PASSWORD="YOUR_NEW_PRIVATE_PASSWORD"
SESSION_COOKIE=ecc_laundry_private_test_session
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_DRIVER=database
CACHE_STORE=database
CACHE_PREFIX=ecc_laundry_private_test
QUEUE_CONNECTION=database
MAIL_MAILER=log
PRIVATE_TEST_ENABLED=true
PRIVATE_TEST_EMAILS=anwar.mounir@centrale-casablanca.ma,yahya.elomari@centrale-casablanca.ma
PRIVATE_TEST_NETWORKS=127.0.0.1/32,::1/128
```

Add the **confirmed school client CIDR ranges** to PRIVATE_TEST_NETWORKS, or use the two testers' current school IP addresses with `/32`. Do not infer a subnet from the server's IP, allow all private networks, or trust arbitrary forwarded headers. The default permits only the server itself.

```bat
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan laundry:create-tester anwar.mounir@centrale-casablanca.ma --admin
php artisan laundry:create-tester yahya.elomari@centrale-casablanca.ma
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The commands ask for private passwords without displaying them, send no emails, and refuse databases whose names do not end in `_test`. Anwar can test administration; Yahya can test the student experience. Registration is blocked server-side, including direct POST requests. Disallowed existing sessions are also blocked.

## 3. Separate Apache listener

Confirm port 8443 is unused. Add a separate Apache configuration file included by Laragon's Apache, without editing the current site's virtual host. Replace certificate placeholders with the existing valid certificate/key paths covering laundry.fecc.ma. Do not copy private keys into the repository.

```apache
Listen 172.28.2.10:8443
<VirtualHost 172.28.2.10:8443>
    ServerName laundry.fecc.ma
    DocumentRoot "C:/laragon/private/laundry-test/public"
    SSLEngine on
    SSLCertificateFile "PATH_TO_VALID_CERTIFICATE"
    SSLCertificateKeyFile "PATH_TO_PRIVATE_KEY"
    <Directory "C:/laragon/private/laundry-test/public">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

Only public/ is served; .env and application source must never be the document root. Run Apache's `httpd -t` using Laragon's active Apache binary before a graceful reload. If no valid certificate is available, finish TLS setup before signing in over the network.

In Windows Firewall, add a NEW inbound TCP 8443 rule restricted to the confirmed school CIDRs/tester IPs and server local IP. Do not open MySQL, change existing production rules, or add router/public port forwarding. Check that an existing broad firewall rule does not already expose 8443. Have school IT verify this listener cannot be reached through the public gateway/reverse proxy.

Open **https://laundry.fecc.ma:8443** from school Wi-Fi/LAN. If school DNS resolves laundry.fecc.ma to a public address without internal routing, add a local hosts entry on each tester's computer mapping laundry.fecc.ma to 172.28.2.10; this does not change DNS for anyone else. Phones may require school DNS support for that name.

## 4. Test-only background processes and verification

Create separate Windows scheduled tasks running in `C:\laragon\private\laundry-test`, using the full path to Laragon's PHP executable:

- Worker at startup, restart on failure: `php artisan queue:work --sleep=3 --tries=3 --timeout=90`.
- Scheduler every minute: `php artisan schedule:run`.

Use the private checkout as each task's working directory. Never point these tasks at production. MAIL_MAILER=log records test emails locally; it does **not** deliver email. Outbound SMTP testing is a separate step requiring credentials and recipient controls.

Verify both accounts can sign in; a third account and /register are rejected; reservations appear only in the test database; the production URL on normal HTTPS still works; a non-allowed source IP is rejected; 8443 is unreachable outside the school. Network gating protects web routes; firewall scoping protects the listener including public assets and health checks.

To stop the test: disable its two scheduled tasks, remove only its Apache listener/config and its dedicated firewall rule, then gracefully reload Apache. Main and production data remain untouched.

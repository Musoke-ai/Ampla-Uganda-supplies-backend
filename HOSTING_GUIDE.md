# Ampla ERP Hosting Guide

Use this checklist when moving the backend from local development to a live server. The safest deployment path is: prepare the server, deploy code, configure environment values, verify migrations on a temporary database, run migrations on production, create the first admin, then run smoke tests.

## 1. Required Server Setup

Backend requirements:

- PHP 8.1 or 8.2 recommended.
- MySQL or MariaDB.
- Apache with `mod_rewrite` enabled, or equivalent Nginx rewrites.
- Composer 2.
- PHP extensions commonly needed by CodeIgniter and this app:
  - `intl`
  - `mbstring`
  - `json`
  - `mysqlnd`
  - `mysqli`
  - `curl`
  - `openssl`
  - `fileinfo`
  - `gd` or `imagick` only if image processing is used.

Recommended production paths:

```text
/var/www/amplaerp/
  app/
  public/
  vendor/
  writable/
  .env
  composer.json
  composer.lock
```

The web server document root must point to:

```text
/var/www/amplaerp/public
```

Never point the public web root at the full project directory. That can expose `.env`, `app/`, `vendor/`, and other private files.

An Apache example is available at:

```text
deploy/apache-public-root.conf.example
```

## 2. Deploy Code

On the server:

```bash
cd /var/www/amplaerp
composer install --no-dev --optimize-autoloader
```

Do not upload local development folders such as:

- `writable/logs/*`
- `writable/debugbar/*`
- local database dumps
- local `.env` containing development secrets
- test-only files unless intentionally needed

Make sure the web server user can write to:

```text
writable/cache
writable/logs
writable/session
writable/uploads
```

Typical Linux permissions:

```bash
chown -R www-data:www-data writable
chmod -R 775 writable
```

Use your host's correct web server user if it is not `www-data`.

## 3. Configure `.env`

Create a production `.env` on the server. Do not reuse exposed local secrets.

Set the environment:

```dotenv
CI_ENVIRONMENT = production
```

Set the backend URL:

```dotenv
app.baseURL = 'https://api.your-domain.com/'
```

Database:

```dotenv
database.default.hostname = your-db-host
database.default.database = your-production-db
database.default.username = your-limited-db-user
database.default.password = your-strong-db-password
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
```

Use a limited database user. Do not use `root` in production.

Secrets to generate or replace:

```dotenv
JWT_SECRET = replace-with-new-random-secret
GROQ_API_KEY = replace-with-live-key-if-copilot-is-enabled
pusher.app_id = replace
pusher.key = replace
pusher.secret = replace
pusher.cluster = replace
CONSUMER_KEY = replace-if-payments-are-live
CONSUMER_SECRET = replace-if-payments-are-live
IPN_ID = replace-if-payments-are-live
```

Remove or disable bootstrap admin values after initial setup:

```dotenv
# ADMIN_USERNAME=
# ADMIN_EMAIL=
# ADMIN_PASSWORD=
# ADMIN_GROUP=
# ADMIN_FORCE=false
```

## 4. Configure CORS

Update `.htaccess` or server config so the API allows only the deployed frontend origin.

Example:

```apache
Header always set Access-Control-Allow-Origin "https://your-frontend-domain.com"
Header always set Access-Control-Allow-Headers "origin, x-requested-with, content-type, Authorization"
Header always set Access-Control-Allow-Methods "PUT, GET, POST, DELETE, OPTIONS"
Header always set Access-Control-Allow-Credentials "true"
```

Do not leave:

```text
http://localhost:3000
```

in production CORS.

## 5. HTTPS And Cookies

Install SSL before live use. The app issues secure refresh-token cookies, so HTTPS is required.

Recommended settings:

- Force HTTPS at the web server level.
- Keep refresh token cookies `secure` and `httponly`.
- Use `SameSite=Lax` if frontend and backend share the same site.
- If frontend and backend are on different sites and cookies must cross site boundaries, review `SameSite=None; Secure` carefully.

In `app/Config/App.php`, production should eventually use:

```php
public bool $forceGlobalSecureRequests = true;
```

Only enable this after SSL and proxy headers are configured correctly.

## 6. Public Route Review

Before launch, review these routes in `app/Config/Routes.php`:

```php
$routes->get('signup', 'Login::signUp');
$routes->get('check', 'Login::checkToken');
$routes->get('makepayment', 'PaymentController::processPayment');
$routes->get('paymentstatus', 'PaymentController::paymentStatus');
```

The email test routes are development-only and should not exist when `CI_ENVIRONMENT = production`:

```php
if (ENVIRONMENT !== 'production') {
    $routes->get('/test-email', 'EmailTest::send');
    $routes->get('sendmail', 'EmailController::sendmail');
}
```

Notification routes must stay behind the JWT filter.

## 7. Verify Migrations Before Production

Before running migrations on the live database, verify the migration chain on a temporary database from the same codebase.

From the backend project root on a machine with MySQL access:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\verify-migrations.ps1
```

This script:

- Creates a temporary database.
- Temporarily updates `.env` to point to it.
- Runs all migrations from zero.
- Runs migrations a second time to confirm no-op behavior.
- Runs migration status.
- Runs PHPUnit unless `-SkipTests` is provided.
- Restores the original `.env`.
- Drops the temporary database unless `-KeepDatabase` is provided.

For a faster migration-only check:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\verify-migrations.ps1 -SkipTests
```

If this script fails, do not run migrations on production until the failing migration is fixed.

## 8. Run Production Migrations

After backup and verification:

```bash
php spark migrate --all
php spark migrate:status
```

Run migrations only after confirming `.env` points to the production database.

Always take a database backup first:

```bash
mysqldump -u db_user -p production_db > backup-before-deploy.sql
```

## 9. Create Initial Admin

Use the CLI command instead of a public setup route:

```bash
php spark admin:create --username=admin --email=admin@example.com --password='StrongPasswordHere' --group=superadmin --force
```

After the admin account is created:

- Remove `ADMIN_PASSWORD` from `.env`.
- Set `ADMIN_FORCE=false` or remove it.
- Keep setup routes disabled.

## 10. Frontend API Configuration

In the frontend deployment environment, set:

```dotenv
REACT_APP_API_BASE_URL=https://api.your-domain.com/api
REACT_APP_AUTH_BASE_URL=https://api.your-domain.com
REACT_APP_PUSHER_KEY=your-key
REACT_APP_PUSHER_CLUSTER=your-cluster
```

Then rebuild the frontend:

```bash
npm run build:node20
```

Deploy the frontend `build/` folder to the frontend host.

## 11. Security Checklist

Before public launch:

- Run `composer audit` and fix critical/high advisories.
- Run `composer install --no-dev --optimize-autoloader`.
- Ensure `.env` is not web-accessible.
- Ensure document root is `public/`.
- Replace all exposed secrets.
- Use a non-root DB user.
- Confirm login throttling is active.
- Review public routes.
- Enable HTTPS.
- Restrict CORS to the production frontend.
- Disable debug toolbar in production.
- Confirm writable directories are not publicly browsable.

Login abuse protection is currently implemented in `LoginController::jwtLogin()`:

- IP limit: 20 attempts per 5 minutes.
- Email limit: 8 attempts per 5 minutes.
- Block response: HTTP 429.

For high-traffic hosting, also add web-server or CDN-level rate limiting.

## 12. Smoke Tests After Deploy

Run these after deployment:

```bash
php spark migrate:status
vendor/bin/phpunit --colors=never
```

Browser/API checks:

- Visit the frontend.
- Log in as superadmin.
- Confirm dashboard loads.
- Confirm Products page loads.
- Create a product.
- Confirm Customers page loads.
- Create a customer.
- Confirm Imports page opens at `/home/imports`.
- Confirm `/api/getcustomers` returns `401` without a bearer token.
- Confirm invalid repeated login attempts eventually return `429`.

## 13. Rollback Plan

Before deploy:

- Backup database.
- Keep previous backend release directory or archive.
- Keep previous frontend build archive.

If deploy fails:

1. Put the site into maintenance mode if possible.
2. Restore previous backend files.
3. Restore previous frontend build.
4. Restore database backup only if migrations changed production schema and rollback is required.
5. Clear cache:

```bash
php spark cache:clear
```

## 14. Useful Commands

Backend checks:

```bash
php -v
composer validate --no-check-publish
composer audit
php spark routes
php spark migrate:status
vendor/bin/phpunit --colors=never
```

Migration verification:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\verify-migrations.ps1
```

Create admin:

```bash
php spark admin:create --username=admin --email=admin@example.com --password='StrongPasswordHere' --group=superadmin --force
```

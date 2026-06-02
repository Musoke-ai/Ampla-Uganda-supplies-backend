# CodeIgniter 4 Application Starter

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

The user guide corresponding to the latest version of the framework can be found
[here](https://codeigniter4.github.io/userguide/).

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

### Admin provisioning for deployment pipelines

You can create the first admin user from CI/CD or deployment pipelines using environment variables:

```powershell
SET ADMIN_USERNAME=admin
SET ADMIN_EMAIL=admin@example.com
SET ADMIN_PASSWORD=StrongPass123
SET ADMIN_GROUP=superadmin
SET ADMIN_FORCE=true
php spark admin:create
```

Or on Linux/macOS:

```bash
export ADMIN_USERNAME=admin
export ADMIN_EMAIL=admin@example.com
export ADMIN_PASSWORD=StrongPass123
export ADMIN_GROUP=superadmin
export ADMIN_FORCE=true
php spark admin:create
```

If the env vars are set in `.env` or in the process environment, the command will use them automatically and will not prompt interactively.

> To use `.env` values automatically, uncomment and provide the `ADMIN_USERNAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` entries in `amplaerp/.env`.

## Clean Test DB Workflow

To verify that the backend can build its schema from migrations without touching your current database, use the helper script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\prepare-clean-test-db.ps1
```

This script will:

- temporarily point `.env` to a fresh database name
- create that database with `php spark db:create`
- run `php spark migrate --all`
- show `php spark migrate:status`
- restore your original `.env` database setting when it finishes

You can also provide your own database name:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\prepare-clean-test-db.ps1 -DatabaseName amplaerp_migration_test_manual
```

Useful options:

- `-CreateOnly` creates the database but skips migrations
- `-KeepEnv` leaves `.env` pointing at the test database after the script finishes

This workflow is intended for local validation on a fresh database. Do not use it against production.

## Existing DB Baseline

If your local database was imported from SQL and already has the tables before these migrations were added, baseline the migration history instead of running `php spark migrate` directly:

```powershell
php .\scripts\baseline-current-db-migrations.php --dry-run
php .\scripts\baseline-current-db-migrations.php
php spark migrate:status
```

What this does:

- checks that the expected app tables already exist
- creates a backup snapshot of the current `migrations` table in `writable/migration-baseline-backups`
- inserts the missing migration history rows without recreating tables or touching their data

Use this only for an already-populated imported database. Avoid `migrate:rollback` on that database unless you intentionally want to remove baseline tables.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 7.4 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> **Warning**
> The end of life date for PHP 7.4 was November 28, 2022. If you are
> still using PHP 7.4, you should upgrade immediately. The end of life date
> for PHP 8.0 will be November 26, 2023.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library

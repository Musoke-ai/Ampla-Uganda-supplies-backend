# Changelog

All notable backend changes will be documented in this file.

## [Unreleased] - 2026-06-08
### Added
- Added authenticated POS cash drawer endpoints for active drawer lookup, history, opening, movements, expenses, and closure.
- Added cash drawer and cash drawer movement models with migrations.
- Added cash sale recording into the active drawer during receipt posting.
- Added receipt settings defaults for template, paper width, and browser/system print mode.
- Added cashier name and branch-aware receipt data in sales retrieval responses.

### Changed
- Updated admin creation defaults to include receipt and notification settings.
- Expanded sales responses with receipt cashier metadata and cash drawer details.

### Fixed
- Prevented Staff Management from returning protected administrator accounts in user-management payloads.
- Blocked deletion of administrator, super administrator, and developer accounts at the API layer.

### Security
- Added `.env.*`, uploads, migration backup, and runtime artifact ignores to reduce accidental secret or data commits.
- Protected the temporary browser migration runner with a required `migration.runner.key`, optional `migration.runner.allowedIp`, environment checks, and redacted error output.
- Deployment note: remove or disable the `run-migrations` route and `Migrate` controller immediately after shared-hosting migrations are complete.

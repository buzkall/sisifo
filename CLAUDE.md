# Sisifo

`arzcode/sisifo` is a Composer **package**, not an application: a Filament v4/v5 plugin that polls an IMAP mailbox, stores messages, and runs LLM-driven mailbox tasks that notify through pluggable channels. There is no `artisan`, no `.env` and no local database here — everything runs through Orchestra Testbench.

## Stack

- PHP ^8.4 (CI runs 8.4 and 8.5), Laravel 13, Filament 4.7+ or 5.2+ (CI runs both)
- Pest 5 on Orchestra Testbench 11, Larastan level 10, Pint
- Laravel AI SDK (`laravel/ai`) for the LLM, webklex/laravel-imap for IMAP, spatie/laravel-settings for settings

## Commands

```bash
composer test                              # Pest
vendor/bin/pest --filter="name"            # a single test
vendor/bin/pint                            # format (CI runs `pint --test`)
vendor/bin/phpstan analyse --no-progress   # static analysis on src/
```

All three must pass before a change is done; CI (`.github/workflows/tests.yml`) runs exactly these.

## Layout

- `src/SisifoServiceProvider.php` — binds contracts, loads migrations/translations, registers publish tags and schedules `mailbox:process` every minute.
- `src/SisifoPlugin.php` — registers the Filament resource on a panel.
- `src/Console/Commands/ProcessMailbox.php` — the engine: throttled IMAP fetch with retries, then watch and summary tasks.
- `src/Models/MailboxTask.php` — scheduling (`isDue`, `whyNotDue`), filters and the per-source item lookup.
- `src/Contracts/` — the extension points: `LlmProvider`, `NotificationChannel`, `SummarizableItem`, `EmbeddingStore`.
- `src/Filament/Resources/MailboxTasks/` — resource split into `Schemas/`, `Tables/`, `Pages/` and `Actions/`.
- `database/migrations/`, `database/settings/` — autoloaded by the provider; also publishable.
- `lang/en`, `lang/es` — every user-facing string, under the `sisifo::` namespace.

## Conventions

- **The engine is source-neutral.** `ProcessMailbox` renders and tracks items only through the `SummarizableItem` contract (`sisifoTitle()`, `sisifoBody()`, …). Never read `InboundEmail` columns from the engine. A new source means a new `mailbox_tasks.source` value plus a branch in both `MailboxTask::getUnprocessedItems()` and `markItemsAsProcessed()`; each source keeps its own processed pivot.
- **This is public API.** Contracts, config keys, env names, publish tags and the `sisifo::` translation keys are consumed by host apps. Changing or removing any of them is a breaking change.
- **Released migrations are immutable.** Host apps have already run them, so schema changes always go in a new migration file. Spatie settings changes go in `database/settings/`.
- **Config must stay cacheable.** No closures or object instances as defaults in `config/sisifo.php`; they break `php artisan config:cache` in the host app.
- **Translations.** Add every new label to both `lang/en/sisifo.php` and `lang/es/sisifo.php`.
- **Changelog.** Record user-visible changes under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog format).
- **Deferred code.** `EmbeddingStore` with its drivers is scaffolding that is not wired into mailbox tasks. Leave it alone unless the task is about it.
- **Dist archive.** New root-level dev files (tool configs, docs for contributors) need an `export-ignore` line in `.gitattributes`.

## Testing

- `tests/TestCase.php` boots the Filament provider stack and a test panel, creates the host tables the package expects (`users`, `notifications`, `settings`, `mailbox_inbound_emails`) on in-memory SQLite, and then runs the package migrations.
- Time is frozen at `2026-06-29 12:00:00` (a Monday) so schedule logic is deterministic. Travel from that point instead of relying on the real clock.
- Use `tests/Support/FakeLlmProvider` and `tests/Support/TestUser`; tests never reach a real LLM or IMAP server.
- Tests run in random order, and warnings or risky tests fail the suite.
- Migrations must work on SQLite as well as MySQL, MariaDB and PostgreSQL.

## IMAP behaviour

`webklex/php-imap` reports any socket failure during `LOGIN` as `AuthFailedException("failed to authenticate")`. Those are retried with a linear backoff; a real server rejection (`ImapServerErrorException`) is never retried. The `mailbox:last_fetch` throttle is armed after every check, successful or not, so a failure cannot make the every-minute schedule hammer the provider. Keep both behaviours when touching the fetch path.

# Changelog

All notable changes to `sisifo` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.8] - 2026-09-07

### Added

- `sisifo.imap.timeout` (default 60s, up from webklex's 30s), `sisifo.imap.retry_attempts` (default 3) and `sisifo.imap.retry_delay_seconds` (default 5, multiplied by the attempt number) to control how `mailbox:process` connects to IMAP.
- Source-neutral `SummarizableItem` contract, so a mailbox task can summarize things other than inbound emails. `mailbox_tasks.source` selects the source; `InboundEmail` implements the contract and remains the default, so existing tasks are unchanged.
- `sisifo.llm.item_body_budget` (default 500 characters) to control per-item body truncation in the LLM input.
- GitHub Actions CI running Pest, Pint and PHPStan.

### Fixed

- Stop treating transient IMAP failures as fatal. `webklex/php-imap` reports any socket failure during `LOGIN` — a dropped connection or a read timeout — as `AuthFailedException("failed to authenticate")`, which is indistinguishable from bad credentials at a glance. The fetch is now retried with a linear backoff, while a real server-side rejection (`ImapServerErrorException`, the server answering `NO`/`BAD`/`BYE`) is never retried.
- Log the whole exception chain (`Mailbox fetch error: AuthFailedException: failed to authenticate <- RuntimeException: empty response`) and pass the exception in the log context, so error trackers get the actual cause instead of the misleading top-level message.
- Arm the `mailbox:last_fetch` throttle after every check rather than only after a successful one. The command is scheduled every minute, so a single failure previously made it reconnect every 60s instead of every `check_every_minutes` — turning one network blip into a burst of failed logins and, with providers that rate limit, into more failures.
- Disconnect the IMAP client once the fetch finishes instead of leaving the connection open until the process exits. Errors while disconnecting are swallowed so they cannot mask the original failure.
- Resolve PHPStan level 5 errors and annotate `InboundEmail`; `notification_methods` nullability corrected.

## [0.1.7] - 2026-07-07

### Fixed

- Truncate the stored email subject to 252 characters so long subjects no longer overflow the column.

## [0.1.6] - 2026-06-30

### Fixed

- Render the `last_result` preview on the Mailbox Task edit page with the Pushover-compatible formatting tags (`<b>`, `<i>`, `<u>`, `<br>`, `<hr>`, `<a href="http...">`) instead of showing raw escaped markup. A new `PushoverHtml::sanitize()` helper escapes everything first and re-allows only that bare tag subset (anchors restricted to `http(s)` hrefs), so stored XSS from AI/email-derived content stays blocked.

## [0.1.5] - 2026-06-29

### Added

- `MAILBOX_IMAP_HOST` as an additional fallback for the IMAP host.

## [0.1.4] - 2026-06-27

### Fixed

- Only register/publish the package views namespace when the `resources/views` directory exists. The empty directory was never shipped in the dist, so `loadViewsFrom` pointed at a missing path and broke `php artisan view:cache` on deploy.

## [0.1.3] - 2026-06-27

### Fixed

- Resolve the database-notification recipient in a cache-safe way: leave `sisifo.notifications.notifiable` null and resolve the user from `notify_user_id` against `notifiable_model` (or the app's auth user model). Avoids the non-serializable closure that broke `php artisan config:cache`. Closures/instances are still accepted for backward compatibility.

### Added

- `sisifo.notifications.notifiable_model` config option to select the model used to resolve `notify_user_id`.

## [0.1.2] - 2026-06-27

### Added

- Configurable Filament navigation group for the Mailbox Task resource via `sisifo.navigation_group` (defaults to `Logistics`, translated at runtime; set to `null` to leave the resource ungrouped).

## [0.1.1] - 2026-06-27

### Fixed

- Escape the `last_result` value on the Mailbox Task edit page to prevent stored XSS from AI/email-derived content (`nl2br(e(...))` instead of raw HTML).

## [0.1.0] - 2026-06-27

### Added

- Initial release.
- Filament v5 plugin (`SisifoPlugin`) registering the Mailbox Task resource.
- IMAP polling via the `mailbox:process` command, persisting messages as `InboundEmail` records, with a configurable fetch interval.
- Mailbox tasks with two types: scheduled **Summary** digests and continuous **Watch** tasks.
- Per-task filtering by sender address, sender domain, subject keywords, and look-back window.
- Swappable `LlmProvider` contract with a Prism PHP driver (Anthropic by default).
- Pluggable `NotificationChannel` contract with Pushover and Filament database channels.
- One-shot tasks, urgent flag, last-result diffing, and a silent "nothing to report" path.
- Shared common prompt via Spatie settings.
- English and Spanish translations.
- `EmbeddingStore` scaffolding (pgvector / MariaDB / MySQL brute-force) for a future memory feature — not yet wired into tasks.

[Unreleased]: https://github.com/buzkall/sisifo/compare/v0.1.4...HEAD
[0.1.4]: https://github.com/buzkall/sisifo/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/buzkall/sisifo/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/buzkall/sisifo/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/buzkall/sisifo/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/buzkall/sisifo/releases/tag/v0.1.0

# Changelog

All notable changes to `sisifo` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/buzkall/sisifo/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/buzkall/sisifo/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/buzkall/sisifo/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/buzkall/sisifo/releases/tag/v0.1.0

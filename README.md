# Sisifo

A Filament v5 plugin that turns an IMAP mailbox into an autonomous, LLM-powered watcher / summarizer.

Sisifo polls a single IMAP account, persists incoming messages, and runs scheduled or watch-style "mailbox tasks" that ask an LLM (Prism PHP by default) to summarize, classify, or extract data — then dispatches notifications through configurable channels (Pushover, Filament database notifications, etc.).

## Installation

```bash
composer require arzcode/sisifo
```

Register the plugin in your Filament panel:

```php
use Arzcode\Sisifo\SisifoPlugin;

$panel->plugin(SisifoPlugin::make());
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=sisifo-config
php artisan vendor:publish --tag=sisifo-migrations
php artisan migrate
```

## Configuration

See `config/sisifo.php` after publishing. Key sections:

- `imap.*` — IMAP connection details
- `llm.*` — LLM provider (driver, provider, model)
- `notifications.{notifiable,channels}` — Notification routing
- `embeddings.dimensions` — Vector dimensions for `sisifo_memories`

## License

MIT

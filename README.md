# Sisifo

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arzcode/sisifo.svg)](https://packagist.org/packages/arzcode/sisifo)
[![License](https://img.shields.io/packagist/l/arzcode/sisifo.svg)](LICENSE.md)

A Filament v5 plugin that turns an IMAP mailbox into an autonomous, LLM-powered watcher and summarizer.

Sisifo polls a single IMAP account, persists incoming messages, and runs scheduled or watch-style **mailbox tasks** that ask an LLM (Prism PHP by default) to summarize, classify, or extract information from new emails — then dispatches notifications through configurable channels (Pushover, Filament database notifications, …).

Like its namesake Sisyphus, it keeps rolling through the inbox so you don't have to.

## Features

- **IMAP polling** — fetches unseen messages on a schedule and stores them as `InboundEmail` records.
- **Two task types**
  - **Summary** — runs on a daily/hourly schedule and digests everything new since it last ran.
  - **Watch** — fires continuously, reacting to matching emails as soon as they arrive.
- **LLM-driven processing** via a swappable `LlmProvider` contract (Prism PHP driver included).
- **Filament resource** to create and manage tasks from your panel.
- **Pluggable notification channels** — Pushover and Filament database notifications out of the box.
- **Per-task filtering** by sender address, sender domain, subject keywords, and look-back window.
- **Smart digests** — tasks remember their last result so the LLM can highlight only what's new, and can stay silent when there's nothing worth reporting.
- **One-shot tasks** that deactivate themselves after firing once.
- English and Spanish translations included.

## Requirements

- PHP 8.3+
- Laravel 13
- Filament 5
- An IMAP-accessible mailbox
- An LLM provider supported by [Prism PHP](https://prismphp.com) (Anthropic by default)

## Installation

```bash
composer require arzcode/sisifo
```

Register the plugin in your Filament panel:

```php
use Arzcode\Sisifo\SisifoPlugin;

$panel->plugin(SisifoPlugin::make());
```

Run the migrations (they are autoloaded by the package, so this is all you need):

```bash
php artisan migrate
```

Publishing is optional and only needed when you want to customize things:

```bash
php artisan vendor:publish --tag=sisifo-config              # config/sisifo.php
php artisan vendor:publish --tag=sisifo-migrations          # database/migrations
php artisan vendor:publish --tag=sisifo-settings-migrations # spatie settings migrations
php artisan vendor:publish --tag=sisifo-lang                # lang/vendor/sisifo
php artisan vendor:publish --tag=sisifo-views               # resources/views/vendor/sisifo
```

## Configuration

Most settings can be driven by environment variables; see `config/sisifo.php` for the full list and defaults.

### IMAP

```dotenv
SISIFO_IMAP_HOST=imap.example.com
SISIFO_IMAP_PORT=993
SISIFO_IMAP_ENCRYPTION=ssl
SISIFO_IMAP_USERNAME=watcher@example.com
SISIFO_IMAP_PASSWORD=secret
```

### LLM

```dotenv
SISIFO_LLM_DRIVER=prism            # 'prism' (default) or 'laravel-ai' (stub, not wired)
SISIFO_LLM_PROVIDER=anthropic
SISIFO_LLM_MODEL=claude-haiku-4-5
SISIFO_LLM_MAX_TOKENS=2048
```

The `prism` driver delegates to [Prism PHP](https://prismphp.com), so configure your provider credentials (e.g. your Anthropic API key) as Prism expects.

### Schedule

```dotenv
SISIFO_CHECK_EVERY_MINUTES=15      # how often to actually hit IMAP
```

The package registers the `mailbox:process` command to run **every minute** (in the environments listed under `sisifo.schedule.environments`, default `production`). The command itself throttles the IMAP fetch to `check_every_minutes`, while task scheduling is evaluated each run. Make sure Laravel's scheduler is active:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Notifications

```php
// config/sisifo.php
'notifications' => [
    // Closure returning the recipient model for database notifications.
    'notifiable' => fn () => \App\Models\User::find(config('sisifo.notify_user_id')),
    'channels'   => [
        \Arzcode\Sisifo\Notifications\Channels\DatabaseNotificationChannel::class,
        \Arzcode\Sisifo\Notifications\Channels\PushoverChannel::class,
    ],
],
```

```dotenv
SISIFO_NOTIFY_USER_ID=1
```

## Usage

Create a **Mailbox Task** from the Filament resource the plugin registers. Each task has:

- **Type** — `Summary` (scheduled digest) or `Watch` (continuous).
- **Prompt** — the instructions handed to the LLM for this task.
- **Schedule** (summary tasks) — `daily` or `hourly`, with optional days of week, time, and timezone.
- **Filters** — `from_addresses`, `from_domains`, `subject_keywords`, and `look_back_days` (default 7).
- **Notification methods** — any combination of the configured channels.
- **Urgent** flag, and a **one-shot** flag to auto-deactivate after a single run.

A shared **common prompt** (stored in settings) is appended to every task's prompt — handy for global tone/format instructions.

If, after analyzing the emails, the LLM decides there is nothing relevant to report, the task stays silent (no notification is sent).

### Running manually

```bash
php artisan mailbox:process              # fetch + run all due tasks
php artisan mailbox:process --task=5     # run a single task by ID, ignoring its schedule
```

## Extending

### Custom LLM provider

Implement the contract and bind it:

```php
use Arzcode\Sisifo\Contracts\LlmProvider;

class MyLlmProvider implements LlmProvider
{
    public function text(string $instructions, string $input, ?int $maxTokens = null): string
    {
        // ...
    }
}

// In a service provider:
$this->app->bind(LlmProvider::class, MyLlmProvider::class);
```

### Custom notification channel

```php
use Arzcode\Sisifo\Contracts\NotificationChannel;
use Arzcode\Sisifo\Models\MailboxTask;

class SlackChannel implements NotificationChannel
{
    public function send(MailboxTask $task, string $title, string $body, bool $urgent): void
    {
        // ...
    }
}
```

Then add it to `sisifo.notifications.channels` (and, if it should be selectable per task, to `MailboxTaskNotificationEnum`).

> **Note:** the `EmbeddingStore` contract and its drivers are scaffolding for a future memory feature and are not yet wired into mailbox tasks.

## Translations

English (`en`) and Spanish (`es`) are bundled. Publish `sisifo-lang` to override strings or add locales.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## License

MIT. See [LICENSE.md](LICENSE.md).

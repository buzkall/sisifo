<?php

use Arzcode\Sisifo\Notifications\Channels\DatabaseNotificationChannel;
use Arzcode\Sisifo\Notifications\Channels\PushoverChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Filament
    |--------------------------------------------------------------------------
    |
    | The navigation group the Mailbox Task resource is placed under in the
    | Filament panel. A string is passed through the translator at runtime, so
    | a translation key works here. Set to null to leave the resource ungrouped.
    |
    */

    'navigation_group' => 'Logistics',

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | How often to poll the IMAP mailbox for new messages.
    |
    */

    'schedule' => [
        'check_every_minutes' => env('SISIFO_CHECK_EVERY_MINUTES', env('MAILBOX_CHECK_EVERY_MINUTES', 15)),
        'environments'        => ['production'],
    ],

    /*
    |--------------------------------------------------------------------------
    | IMAP Connection
    |--------------------------------------------------------------------------
    */

    'imap' => [
        'host'          => env('SISIFO_IMAP_HOST', env('MAILBOX_HOST')),
        'port'          => env('SISIFO_IMAP_PORT', env('MAILBOX_PORT', 993)),
        'encryption'    => env('SISIFO_IMAP_ENCRYPTION', env('MAILBOX_ENCRYPTION', 'ssl')),
        'validate_cert' => env('SISIFO_IMAP_VALIDATE_CERT', env('MAILBOX_VALIDATE_CERT', true)),
        'username'      => env('SISIFO_IMAP_USERNAME', env('MAILBOX_USERNAME')),
        'password'      => env('SISIFO_IMAP_PASSWORD', env('MAILBOX_PASSWORD')),
        'protocol'      => env('SISIFO_IMAP_PROTOCOL', 'imap'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Provider
    |--------------------------------------------------------------------------
    |
    | Selects which LlmProvider implementation gets bound in the container.
    | Supported drivers: 'prism' (Prism PHP), 'laravel-ai' (stub — not wired).
    |
    */

    'llm' => [
        'driver'     => env('SISIFO_LLM_DRIVER', 'prism'),
        'provider'   => env('SISIFO_LLM_PROVIDER', 'anthropic'),
        'model'      => env('SISIFO_LLM_MODEL', 'claude-haiku-4-5'),
        'max_tokens' => (int)env('SISIFO_LLM_MAX_TOKENS', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Configures the EmbeddingStore. The driver is selected automatically
    | from the default DB connection (pgsql, mariadb, mysql).
    |
    */

    'embeddings' => [
        'dimensions' => (int)env('SISIFO_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The database channel recipient. Leave `notifiable` null (cache-safe) to
    | resolve the user from `notify_user_id` against `notifiable_model` — or the
    | app's auth user model when that is null. A closure or notifiable instance
    | is also accepted, but breaks `php artisan config:cache`, so avoid it.
    | `channels` is the list of NotificationChannel classes fired by a task.
    |
    */

    'notifications' => [
        'notifiable'       => null,
        'notifiable_model' => null,
        'channels'         => [
            DatabaseNotificationChannel::class,
            PushoverChannel::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recipient
    |--------------------------------------------------------------------------
    */

    'notify_user_id' => env('SISIFO_NOTIFY_USER_ID', env('MAILBOX_NOTIFY_USER_ID')),

];

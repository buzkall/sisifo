<?php

namespace Arzcode\Sisifo\Notifications\Channels;

use Arzcode\Sisifo\Contracts\NotificationChannel;
use Arzcode\Sisifo\Models\MailboxTask;
use Filament\Notifications\Notification;

class DatabaseNotificationChannel implements NotificationChannel
{
    public function send(MailboxTask $task, string $title, string $body, bool $urgent): void
    {
        $notifiable = $this->resolveNotifiable();

        if ($notifiable === null) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->sendToDatabase($notifiable);
    }

    /**
     * Resolve the database-notification recipient.
     *
     * The recipient may be configured as a callable or an already-resolved
     * notifiable instance, but those are NOT serializable and break
     * `php artisan config:cache`. The cache-safe default is to leave
     * `sisifo.notifications.notifiable` null and resolve the user from
     * `sisifo.notify_user_id` against the configured notifiable model
     * (falling back to the application's auth user model).
     */
    private function resolveNotifiable(): mixed
    {
        $resolver = config('sisifo.notifications.notifiable');

        if (is_callable($resolver)) {
            return $resolver();
        }

        if (is_object($resolver)) {
            return $resolver;
        }

        $userId = config('sisifo.notify_user_id');

        if ($userId === null) {
            return null;
        }

        $model = config('sisifo.notifications.notifiable_model')
            ?? config('auth.providers.users.model');

        return is_string($model) && class_exists($model)
            ? $model::find($userId)
            : null;
    }
}

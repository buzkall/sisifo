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

    private function resolveNotifiable(): mixed
    {
        $resolver = config('sisifo.notifications.notifiable');

        if (! is_callable($resolver)) {
            return null;
        }

        return $resolver();
    }
}

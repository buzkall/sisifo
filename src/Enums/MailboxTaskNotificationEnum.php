<?php

namespace Arzcode\Sisifo\Enums;

use Arzcode\Sisifo\Notifications\Channels\DatabaseNotificationChannel;
use Arzcode\Sisifo\Notifications\Channels\PushoverChannel;
use Arzcode\Sisifo\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum MailboxTaskNotificationEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Pushover = 'pushover';
    case Database = 'database';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pushover => __('sisifo::sisifo.notification_pushover'),
            self::Database => __('sisifo::sisifo.notification_database'),
        };
    }

    public function toChannelClass(): string
    {
        return match ($this) {
            self::Pushover => PushoverChannel::class,
            self::Database => DatabaseNotificationChannel::class,
        };
    }
}

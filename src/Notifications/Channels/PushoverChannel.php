<?php

namespace Arzcode\Sisifo\Notifications\Channels;

use Arzcode\Sisifo\Contracts\NotificationChannel;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;

class PushoverChannel implements NotificationChannel
{
    public function __construct(private readonly PushoverService $pushover) {}

    public function send(MailboxTask $task, string $title, string $body, bool $urgent): void
    {
        $this->pushover->send($body, $title, $urgent ? 1 : -1);
    }
}

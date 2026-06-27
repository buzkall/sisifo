<?php

namespace Arzcode\Sisifo\Contracts;

use Arzcode\Sisifo\Models\MailboxTask;

interface NotificationChannel
{
    public function send(MailboxTask $task, string $title, string $body, bool $urgent): void;
}

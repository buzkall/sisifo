<?php

namespace Arzcode\Sisifo\Settings;

use Spatie\LaravelSettings\Settings;

class MailboxSettings extends Settings
{
    public string $common_prompt;

    public static function group(): string
    {
        return 'mailbox';
    }
}

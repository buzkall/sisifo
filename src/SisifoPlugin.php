<?php

namespace Arzcode\Sisifo;

use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class SisifoPlugin implements Plugin
{
    public static function make(): static
    {
        /** @var static $plugin */
        $plugin = app(static::class);

        return $plugin;
    }

    public function getId(): string
    {
        return 'sisifo';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MailboxTaskResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

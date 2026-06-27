<?php

namespace Arzcode\Sisifo\Enums;

use Arzcode\Sisifo\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum MailboxTaskTypeEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Summary = 'summary';
    case Watch = 'watch';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Summary => __('sisifo::sisifo.type_summary'),
            self::Watch   => __('sisifo::sisifo.type_watch'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Summary => 'info',
            self::Watch   => 'warning',
        };
    }
}

<?php

namespace Arzcode\Sisifo\Enums;

use Arzcode\Sisifo\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum MailboxTaskSourceEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case InboundEmail = 'inbound_email';
    case GithubReleases = 'github_releases';

    public function getLabel(): string
    {
        return match ($this) {
            self::InboundEmail   => __('sisifo::sisifo.source_inbound_email'),
            self::GithubReleases => __('sisifo::sisifo.source_github_releases'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InboundEmail   => 'gray',
            self::GithubReleases => 'success',
        };
    }
}

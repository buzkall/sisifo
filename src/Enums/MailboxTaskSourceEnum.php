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

    /**
     * The field names each item is rendered under in the LLM input. The email
     * ones are the engine's original wording and must not change.
     *
     * @return array{origin: string, title: string, date: string, body: string}
     */
    public function itemLabels(): array
    {
        return match ($this) {
            self::InboundEmail   => ['origin' => 'De', 'title' => 'Asunto', 'date' => 'Fecha', 'body' => 'Contenido'],
            self::GithubReleases => ['origin' => 'Repositorio', 'title' => 'Versión', 'date' => 'Fecha', 'body' => 'Notas'],
        };
    }

    /**
     * What the items are called in the instructions given to the LLM.
     */
    public function itemNoun(): string
    {
        return match ($this) {
            self::InboundEmail   => 'emails',
            self::GithubReleases => 'releases',
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

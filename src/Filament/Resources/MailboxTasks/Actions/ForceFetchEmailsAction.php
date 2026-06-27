<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions;

use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Models\MailboxTask;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ForceFetchEmailsAction
{
    public static function make(): Action
    {
        return Action::make('forceFetch')
            ->label(__('sisifo::sisifo.force_fetch_emails'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn(MailboxTask $record): bool => $record->type === MailboxTaskTypeEnum::Watch)
            ->requiresConfirmation()
            ->modalDescription(__('sisifo::sisifo.force_fetch_confirm'))
            ->action(function(MailboxTask $record, Component $livewire): void {
                Cache::forget('mailbox:last_fetch');

                RunMailboxTaskAction::execute($record, $livewire);
            });
    }
}

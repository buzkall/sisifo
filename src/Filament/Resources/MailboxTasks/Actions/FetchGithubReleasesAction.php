<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions;

use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\GithubReleases\GithubReleasesIngestor;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class FetchGithubReleasesAction
{
    public static function make(): Action
    {
        return Action::make('fetchReleases')
            ->label(__('sisifo::sisifo.fetch_releases'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn(MailboxTask $record): bool => $record->source === MailboxTaskSourceEnum::GithubReleases
                && $record->source_ref !== null)
            ->requiresConfirmation()
            ->modalDescription(__('sisifo::sisifo.fetch_releases_confirm'))
            ->action(function(MailboxTask $record, Component $livewire): void {
                try {
                    // A private, renamed or misspelled repo 404s here.
                    $new = app(GithubReleasesIngestor::class)->ingest((string)$record->source_ref);
                } catch (Exception $e) {
                    Notification::make()
                        ->title(__('sisifo::sisifo.fetch_releases_error', ['repo' => $record->source_ref]))
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(trans_choice('sisifo::sisifo.fetch_releases_done', $new, ['count' => $new]))
                    ->success()
                    ->send();

                RunMailboxTaskAction::execute($record, $livewire);
            });
    }
}

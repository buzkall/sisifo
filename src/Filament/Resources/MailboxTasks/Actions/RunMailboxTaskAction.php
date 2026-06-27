<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions;

use Arzcode\Sisifo\Models\MailboxTask;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class RunMailboxTaskAction
{
    public static function make(): Action
    {
        return Action::make('run')
            ->label(__('sisifo::sisifo.run_now'))
            ->icon('heroicon-o-play')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('sisifo::sisifo.run_now_confirm'))
            ->action(fn(MailboxTask $record, Component $livewire) => self::execute($record, $livewire));
    }

    public static function execute(MailboxTask $record, Component $livewire): void
    {
        $previousResult = $record->last_result;
        $exitCode = Artisan::call('mailbox:process', ['--task' => $record->getKey()]);
        $output = trim(Artisan::output());

        $record->refresh();

        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData(['last_run_at', 'last_result', 'is_active']);
        }

        if ($exitCode !== 0) {
            Notification::make()
                ->title(__('sisifo::sisifo.error_executing_task'))
                ->body($output ?: __('See logs for details'))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($record->last_result !== $previousResult && $record->last_result) {
            Notification::make()
                ->title(__('sisifo::sisifo.task_executed'))
                ->body($record->last_result)
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('sisifo::sisifo.no_pending_emails'))
            ->warning()
            ->send();
    }
}

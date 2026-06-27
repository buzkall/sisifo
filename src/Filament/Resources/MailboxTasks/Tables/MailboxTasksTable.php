<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Tables;

use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions\RunMailboxTaskAction;
use Arzcode\Sisifo\Models\MailboxTask;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MailboxTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('sisifo::sisifo.name'))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('sisifo::sisifo.type'))
                    ->badge()
                    ->color(fn(MailboxTaskTypeEnum $state): string => $state->getColor()),

                ToggleColumn::make('is_active')
                    ->label(__('sisifo::sisifo.active')),

                TextColumn::make('schedule_frequency')
                    ->label(__('sisifo::sisifo.schedule'))
                    ->sortable(false)
                    ->state(function(MailboxTask $record): string {
                        if ($record->schedule_frequency === 'hourly') {
                            return __('Hourly');
                        }

                        $dayKeys = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
                        $days = collect($record->schedule_days ?? [])
                            ->sort()
                            ->map(fn(int $day) => isset($dayKeys[$day]) ? __('sisifo::sisifo.' . $dayKeys[$day]) : '')
                            ->implode(', ');

                        return $days ?: '-';
                    })
                    ->description(fn(MailboxTask $record): string => $record->schedule_time
                        ? __('sisifo::sisifo.at_time', ['time' => $record->schedule_time])
                        : ''),

                TextColumn::make('notification_methods')
                    ->label(__('sisifo::sisifo.notification_method'))
                    ->badge()
                    ->sortable(false),

                IconColumn::make('is_urgent')
                    ->label(__('sisifo::sisifo.urgent'))
                    ->boolean()
                    ->trueIcon('heroicon-o-bell-alert')
                    ->falseIcon('heroicon-o-bell-snooze'),

                TextColumn::make('last_run_at')
                    ->label(__('sisifo::sisifo.last_run'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('sisifo::sisifo.never')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->iconButton(),

                ReplicateAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-document-duplicate')
                    ->label(__('sisifo::sisifo.duplicate'))
                    ->modal(false)
                    ->successNotificationTitle(__('sisifo::sisifo.task_duplicated'))
                    ->excludeAttributes(['last_run_at', 'last_result'])
                    ->beforeReplicaSaved(function(MailboxTask $replica, MailboxTask $record): void {
                        $replica->name = $record->name . __('sisifo::sisifo.copy_suffix');
                        $replica->is_active = false;
                    }),

                RunMailboxTaskAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}

<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages;

use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMailboxTasks extends ListRecords
{
    protected static string $resource = MailboxTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editCommonPrompt')
                ->label(__('sisifo::sisifo.common_instructions'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->modalHeading(__('sisifo::sisifo.common_instructions'))
                ->modalDescription(__('sisifo::sisifo.common_instructions_help'))
                ->fillForm(fn(): array => [
                    'common_prompt' => app(MailboxSettings::class)->common_prompt,
                ])
                ->schema([
                    Textarea::make('common_prompt')
                        ->label(__('sisifo::sisifo.common_instructions'))
                        ->rows(10)
                        ->autosize()
                        ->required(),
                ])
                ->action(function(array $data): void {
                    $settings = app(MailboxSettings::class);
                    $settings->common_prompt = $data['common_prompt'];
                    $settings->save();

                    Notification::make()
                        ->title(__('sisifo::sisifo.common_instructions_saved'))
                        ->success()
                        ->send();
                })
                ->modalSubmitActionLabel(__('sisifo::sisifo.save')),

            CreateAction::make(),
        ];
    }
}

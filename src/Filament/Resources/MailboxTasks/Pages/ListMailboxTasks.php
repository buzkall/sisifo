<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages;

use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

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
                ->fillForm(fn(): array => collect(MailboxTaskSourceEnum::cases())
                    ->mapWithKeys(fn(MailboxTaskSourceEnum $source): array => [
                        $source->value => app(MailboxSettings::class)->promptFor($source),
                    ])
                    ->all())
                ->schema([
                    Tabs::make()->tabs(
                        collect(MailboxTaskSourceEnum::cases())
                            ->map(fn(MailboxTaskSourceEnum $source): Tab => Tab::make($source->getLabel())
                                ->schema([
                                    Textarea::make($source->value)
                                        ->label(__('sisifo::sisifo.common_instructions'))
                                        ->helperText(__('sisifo::sisifo.common_instructions_source_help', [
                                            'source' => $source->getLabel(),
                                        ]))
                                        ->rows(10)
                                        ->autosize(),
                                ]))
                            ->all()
                    ),
                ])
                ->action(function(array $data): void {
                    $settings = app(MailboxSettings::class);

                    foreach (MailboxTaskSourceEnum::cases() as $source) {
                        $prompt = $data[$source->value] ?? '';

                        $settings->setPromptFor($source, is_string($prompt) ? $prompt : '');
                    }

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

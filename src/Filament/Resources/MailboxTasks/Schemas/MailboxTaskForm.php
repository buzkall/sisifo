<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Schemas;

use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Arzcode\Sisifo\Support\PushoverHtml;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MailboxTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Section::make(__('sisifo::sisifo.general_settings'))
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('sisifo::sisifo.name'))
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label(__('sisifo::sisifo.type'))
                            ->options(MailboxTaskTypeEnum::class)
                            ->required()
                            ->live()
                            ->default(MailboxTaskTypeEnum::Summary),

                        Textarea::make('prompt')
                            ->label(__('sisifo::sisifo.ai_instructions'))
                            ->required()
                            ->rows(6)
                            ->autosize()
                            ->columnSpanFull(),

                        Callout::make(__('sisifo::sisifo.common_instructions'))
                            ->description(fn(): HtmlString => new HtmlString(
                                nl2br(e(app(MailboxSettings::class)->common_prompt ?: '-'))
                            ))
                            ->color('gray')
                            ->columnSpanFull(),

                        Callout::make(__('sisifo::sisifo.skip_when_nothing_to_report'))
                            ->description(__('sisifo::sisifo.skip_when_nothing_help'))
                            ->color('gray')
                            ->columnSpanFull(),

                        Select::make('notification_methods')
                            ->label(__('sisifo::sisifo.notification_methods'))
                            ->options(MailboxTaskNotificationEnum::class)
                            ->default([MailboxTaskNotificationEnum::Pushover->value])
                            ->multiple()
                            ->required(),

                        Toggle::make('is_urgent')
                            ->label(__('sisifo::sisifo.urgent_notification'))
                            ->helperText(__('sisifo::sisifo.urgent_notification_help'))
                            ->default(false),
                    ]),

                Section::make(__('sisifo::sisifo.status_and_schedule'))
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('sisifo::sisifo.active'))
                            ->default(true),

                        Select::make('schedule_frequency')
                            ->label(__('sisifo::sisifo.frequency'))
                            ->options([
                                'daily'  => __('sisifo::sisifo.daily_label'),
                                'hourly' => __('Hourly'),
                            ])
                            ->default('daily')
                            ->live()
                            ->visible(fn(Get $get): bool => $get('type') === MailboxTaskTypeEnum::Summary),

                        ToggleButtons::make('schedule_days')
                            ->label(__('sisifo::sisifo.days_of_week'))
                            ->multiple()
                            ->grouped()
                            ->options([
                                1 => __('sisifo::sisifo.mon'),
                                2 => __('sisifo::sisifo.tue'),
                                3 => __('sisifo::sisifo.wed'),
                                4 => __('sisifo::sisifo.thu'),
                                5 => __('sisifo::sisifo.fri'),
                                6 => __('sisifo::sisifo.sat'),
                                7 => __('sisifo::sisifo.sun'),
                            ])
                            ->default([1, 2, 3, 4, 5])
                            ->visible(fn(Get $get): bool => $get('type') === MailboxTaskTypeEnum::Summary),

                        TimePicker::make('schedule_time')
                            ->label(__('sisifo::sisifo.time'))
                            ->default('09:00')
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->visible(fn(Get $get): bool => $get('type') === MailboxTaskTypeEnum::Summary
                                && $get('schedule_frequency') === 'daily'),

                        Select::make('schedule_timezone')
                            ->label(__('sisifo::sisifo.timezone'))
                            ->options(fn() => collect(timezone_identifiers_list())
                                ->mapWithKeys(fn(string $tz) => [$tz => $tz]))
                            ->searchable()
                            ->default('Europe/Madrid')
                            ->visible(fn(Get $get): bool => $get('type') === MailboxTaskTypeEnum::Summary),

                        Toggle::make('one_shot')
                            ->label(__('sisifo::sisifo.deactivate_after_first_alert'))
                            ->helperText(__('sisifo::sisifo.deactivate_after_first_help'))
                            ->default(false)
                            ->visible(fn(Get $get): bool => $get('type') === MailboxTaskTypeEnum::Watch),

                        TextEntry::make('last_run_at')
                            ->label(__('sisifo::sisifo.last_run'))
                            ->dateTime('d/m/Y H:i:s')
                            ->hiddenOn('create'),

                        Callout::make(__('sisifo::sisifo.last_result'))
                            ->description(fn($record) => $record->last_result
                                ? new HtmlString(PushoverHtml::sanitize($record->last_result))
                                : '-')
                            ->info()
                            ->hiddenOn('create'),
                    ]),
            ])->columnSpanFull(),

            Section::make(__('sisifo::sisifo.filters'))
                ->description(__('sisifo::sisifo.leave_empty_all_emails'))
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TagsInput::make('filters.from_addresses')
                            ->label(__('sisifo::sisifo.sender_addresses'))
                            ->placeholder('email@example.com')
                            ->splitKeys(['Tab', ',', ' ']),

                        TagsInput::make('filters.from_domains')
                            ->label(__('sisifo::sisifo.sender_domains'))
                            ->placeholder('example.com')
                            ->splitKeys(['Tab', ',', ' ']),

                        TagsInput::make('filters.subject_keywords')
                            ->label(__('sisifo::sisifo.subject_keywords'))
                            ->placeholder(__('sisifo::sisifo.urgent'))
                            ->splitKeys(['Tab', ',', ' ']),

                        TextInput::make('filters.look_back_days')
                            ->label(__('sisifo::sisifo.lookback_days'))
                            ->numeric()
                            ->default(7)
                            ->minValue(1)
                            ->maxValue(90),
                    ]),
                ])->columnSpanFull(),
        ]);
    }
}

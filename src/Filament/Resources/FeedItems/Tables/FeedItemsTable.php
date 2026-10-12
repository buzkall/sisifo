<?php

namespace Arzcode\Sisifo\Filament\Resources\FeedItems\Tables;

use Arzcode\Sisifo\Models\FeedItem;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeedItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('sisifo::sisifo.feed_item_title'))
                    ->searchable()
                    ->wrap()
                    ->url(fn(FeedItem $record): string => $record->url, shouldOpenInNewTab: true),

                TextColumn::make('source_ref')
                    ->label(__('sisifo::sisifo.feed_item_repository'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('published_at')
                    ->label(__('sisifo::sisifo.feed_item_published_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder(__('sisifo::sisifo.never')),

                // A zero here explains a task reporting nothing new.
                TextColumn::make('mailbox_tasks_count')
                    ->label(__('sisifo::sisifo.feed_item_processed_by'))
                    ->counts('mailboxTasks')
                    ->badge()
                    ->sortable(false),
            ])
            ->filters([
                SelectFilter::make('source_ref')
                    ->label(__('sisifo::sisifo.feed_item_repository'))
                    ->options(fn(): array => FeedItem::query()
                        ->distinct()
                        ->orderBy('source_ref')
                        ->pluck('source_ref', 'source_ref')
                        ->all()),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalHeading(fn(FeedItem $record): string => $record->title)
                    ->modalSubmitAction(false)
                    ->schema(fn(Schema $schema): Schema => $schema->components([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->markdown(),
                    ])),
            ]);
    }
}

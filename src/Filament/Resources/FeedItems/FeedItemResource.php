<?php

namespace Arzcode\Sisifo\Filament\Resources\FeedItems;

use Arzcode\Sisifo\Filament\Concerns\HasSisifoNavigation;
use Arzcode\Sisifo\Filament\Resources\FeedItems\Pages\ListFeedItems;
use Arzcode\Sisifo\Filament\Resources\FeedItems\Tables\FeedItemsTable;
use Arzcode\Sisifo\Models\FeedItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only window onto the entries Sisifo has ingested from feed sources, so
 * an empty or surprising summary can be traced back to what was actually
 * fetched. Records are written by the ingestor, never by hand.
 */
class FeedItemResource extends Resource
{
    use HasSisifoNavigation;

    protected static ?string $model = FeedItem::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $recordTitleAttribute = 'title';
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getModelLabel(): string
    {
        return __('sisifo::sisifo.feed_item');
    }

    public static function getPluralLabel(): string
    {
        return __('sisifo::sisifo.feed_items');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return FeedItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedItems::route('/'),
        ];
    }
}

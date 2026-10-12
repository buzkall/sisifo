<?php

use Arzcode\Sisifo\Filament\Resources\FeedItems\FeedItemResource;
use Arzcode\Sisifo\Filament\Resources\FeedItems\Pages\ListFeedItems;
use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Tests\Support\TestUser;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function() {
    $this->user = TestUser::factory()->create();
    actingAs($this->user);

    FeedItem::query()->delete();
});

test('ListFeedItems page renders', function() {
    Livewire::test(ListFeedItems::class)->assertSuccessful();
});

test('ListFeedItems lists ingested releases', function() {
    $item = FeedItem::factory()->create([
        'title'      => 'v4.1.0',
        'source_ref' => 'filamentphp/filament',
    ]);

    Livewire::test(ListFeedItems::class)
        ->assertCanSeeTableRecords([$item])
        ->assertSee('filamentphp/filament');
});

test('feed items are read-only', function() {
    expect(FeedItemResource::canCreate())->toBeFalse()
        ->and(FeedItemResource::getPages())->toHaveKeys(['index'])
        ->and(FeedItemResource::getPages())->toHaveCount(1);

    // A deleted item still in the feed would be re-ingested and reported again.
    Livewire::test(ListFeedItems::class)->assertTableBulkActionDoesNotExist('delete');
});

test('navigation group follows the shared sisifo config', function() {
    config()->set('sisifo.navigation_group', 'Operations');

    expect(FeedItemResource::getNavigationGroup())->toBe('Operations');
});

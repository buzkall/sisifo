<?php

use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Services\GithubReleases\GithubReleasesFeed;
use Arzcode\Sisifo\Services\GithubReleases\GithubReleasesIngestor;
use Illuminate\Support\Facades\Http;

function fakeReleasesFeed(): void
{
    $atom = file_get_contents(__DIR__ . '/../Fixtures/filament-releases.atom');

    Http::fake([
        'github.com/*' => Http::response($atom, 200, ['Content-Type' => 'application/atom+xml']),
    ]);
}

it('parses each atom entry into normalized data', function() {
    fakeReleasesFeed();

    $entries = app(GithubReleasesFeed::class)->fetch('filamentphp/filament');

    // The fetcher returns every entry; skipping happens during ingestion.
    expect($entries)->toHaveCount(3);

    $stable = $entries->firstWhere('title', 'v5.6.8');

    expect($stable['external_id'])->toBe('tag:github.com,2008:Repository/128923097/v5.6.8')
        ->and($stable['url'])->toBe('https://github.com/filamentphp/filament/releases/tag/v5.6.8')
        ->and($stable['source_ref'])->toBe('filamentphp/filament')
        ->and($stable['body'])->toContain("What's Changed")
        ->and($stable['body'])->toContain('ENDOFCHANGELOG568')
        ->and($stable['body'])->not->toContain('<li>')
        ->and($stable['published_at']->toDateString())->toBe('2026-07-10');
});

it('hits the anonymous releases.atom endpoint with no token', function() {
    fakeReleasesFeed();

    app(GithubReleasesFeed::class)->fetch('filamentphp/filament');

    Http::assertSent(function($request) {
        return $request->url() === 'https://github.com/filamentphp/filament/releases.atom'
            && ! $request->hasHeader('Authorization');
    });
});

it('ingests releases into feed items mapped to honest columns', function() {
    fakeReleasesFeed();

    app(GithubReleasesIngestor::class)->ingest('filamentphp/filament');

    $item = FeedItem::where('title', 'v5.6.8')->firstOrFail();

    expect($item->external_id)->toBe('tag:github.com,2008:Repository/128923097/v5.6.8')
        ->and($item->url)->toBe('https://github.com/filamentphp/filament/releases/tag/v5.6.8')
        ->and($item->source_ref)->toBe('filamentphp/filament')
        ->and($item->body)->toContain('ENDOFCHANGELOG568')
        ->and($item->published_at->toDateString())->toBe('2026-07-10');
});

it('skips near-empty beta changelogs', function() {
    fakeReleasesFeed();

    app(GithubReleasesIngestor::class)->ingest('filamentphp/filament');

    // v5.7.0-beta4 renders as "No content." and must not be stored.
    expect(FeedItem::count())->toBe(2)
        ->and(FeedItem::where('title', 'v5.7.0-beta4')->exists())->toBeFalse();
});

it('dedupes on re-fetch so a second run ingests nothing new', function() {
    fakeReleasesFeed();

    app(GithubReleasesIngestor::class)->ingest('filamentphp/filament');
    app(GithubReleasesIngestor::class)->ingest('filamentphp/filament');

    expect(FeedItem::count())->toBe(2);
});

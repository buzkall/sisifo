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

it('keeps a release whose notes are only a few words', function() {
    Http::fake(['github.com/*' => Http::response(<<<'ATOM'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <entry>
            <id>tag:github.com,2008:Repository/1/v1.0.1</id>
            <updated>2026-07-01T10:00:00Z</updated>
            <link rel="alternate" type="text/html" href="https://github.com/acme/tool/releases/tag/v1.0.1"/>
            <title>v1.0.1</title>
            <content type="html">&lt;p&gt;Bug fixes.&lt;/p&gt;</content>
          </entry>
        </feed>
        ATOM)]);

    app(GithubReleasesIngestor::class)->ingest('acme/tool');

    expect(FeedItem::where('title', 'v1.0.1')->value('body'))->toBe('Bug fixes.');
});

it('skips releases published without notes', function() {
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

it('reports how many releases each run actually stored', function() {
    fakeReleasesFeed();

    expect(app(GithubReleasesIngestor::class)->ingest('filamentphp/filament'))->toBe(2)
        ->and(app(GithubReleasesIngestor::class)->ingest('filamentphp/filament'))->toBe(0);
});

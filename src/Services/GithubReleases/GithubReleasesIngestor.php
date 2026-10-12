<?php

namespace Arzcode\Sisifo\Services\GithubReleases;

use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Support\ConfigValue;
use Illuminate\Support\Facades\Cache;

/**
 * Fetches a repository's releases and persists them into `sisifo_feed_items`,
 * deduped by external id. Releases published without notes (typically betas)
 * are skipped so tasks never notify on an empty release.
 */
class GithubReleasesIngestor
{
    /**
     * What GitHub renders in the feed for a release published without notes.
     */
    private const NO_NOTES_PLACEHOLDER = 'No content.';

    public function __construct(private readonly GithubReleasesFeed $feed) {}

    /**
     * Ingest unless this repository was already fetched within
     * `sisifo.sources.github_releases.check_every_minutes`. Watch tasks run on
     * every scheduler tick, so without this each one would hit github.com once
     * a minute.
     *
     * @return int|null the number of releases newly stored, or null when throttled
     */
    public function ingestIfDue(string $repo): ?int
    {
        if (Cache::has($this->throttleKey($repo))) {
            return null;
        }

        return $this->ingest($repo);
    }

    /**
     * @return int the number of releases newly stored by this call
     */
    public function ingest(string $repo): int
    {
        // Armed before fetching, so a failing repository is throttled too
        // instead of being retried on every tick.
        Cache::put(
            $this->throttleKey($repo),
            now()->toIso8601String(),
            now()->addMinutes(ConfigValue::int('sisifo.sources.github_releases.check_every_minutes', 60)),
        );

        return $this->feed->fetch($repo)
            ->reject(fn(array $entry): bool => in_array($entry['body'], ['', self::NO_NOTES_PLACEHOLDER], true))
            ->map(fn(array $entry): FeedItem => FeedItem::firstOrCreate(
                ['external_id' => $entry['external_id']],
                [
                    'title'        => $entry['title'],
                    'url'          => $entry['url'],
                    'source_ref'   => $entry['source_ref'],
                    'body'         => $entry['body'],
                    'published_at' => $entry['published_at'],
                ],
            ))
            ->filter(fn(FeedItem $item): bool => $item->wasRecentlyCreated)
            ->count();
    }

    private function throttleKey(string $repo): string
    {
        return 'sisifo:github_releases:last_fetch:' . strtolower($repo);
    }
}

<?php

namespace Arzcode\Sisifo\Services\GithubReleases;

use Arzcode\Sisifo\Models\FeedItem;

/**
 * Fetches a repository's releases and persists them into `sisifo_feed_items`,
 * deduped by external id. Near-empty changelogs (pre-releases/betas that render
 * as "No content.") are skipped so tasks never notify on an empty release.
 */
class GithubReleasesIngestor
{
    /**
     * Minimum changelog length (in characters, after markdown conversion) for an
     * entry to be worth storing. GitHub renders a release with no notes as the
     * literal "No content." (11 chars), so anything shorter than this threshold
     * is treated as empty and skipped.
     */
    private const MIN_BODY_LENGTH = 20;

    public function __construct(private readonly GithubReleasesFeed $feed) {}

    public function ingest(string $repo): void
    {
        $this->feed->fetch($repo)
            ->reject(fn(array $entry): bool => mb_strlen($entry['body']) < self::MIN_BODY_LENGTH)
            ->each(fn(array $entry) => FeedItem::firstOrCreate(
                ['external_id' => $entry['external_id']],
                [
                    'title'        => $entry['title'],
                    'url'          => $entry['url'],
                    'source_ref'   => $entry['source_ref'],
                    'body'         => $entry['body'],
                    'published_at' => $entry['published_at'],
                ],
            ));
    }
}

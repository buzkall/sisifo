<?php

namespace Arzcode\Sisifo\Services\GithubReleases;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use League\HTMLToMarkdown\HtmlConverter;
use SimpleXMLElement;

/**
 * Fetches a public repository's GitHub Releases Atom feed and normalizes each
 * entry. No token and no REST API — just the anonymous
 * https://github.com/{owner}/{repo}/releases.atom document.
 */
class GithubReleasesFeed
{
    /**
     * Fetch and normalize the releases of `owner/repo`.
     *
     * @return Collection<int, array{external_id: string, title: string, url: string, source_ref: string, body: string, published_at: CarbonInterface}>
     */
    public function fetch(string $repo): Collection
    {
        $response = Http::get("https://github.com/{$repo}/releases.atom");
        $response->throw();

        return $this->parse($response->body(), $repo);
    }

    /**
     * @return Collection<int, array{external_id: string, title: string, url: string, source_ref: string, body: string, published_at: CarbonInterface}>
     */
    private function parse(string $body, string $repo): Collection
    {
        $xml = @simplexml_load_string($body);

        if (! $xml instanceof SimpleXMLElement) {
            return collect();
        }

        $converter = new HtmlConverter([
            'strip_tags'   => true,
            'remove_nodes' => 'style script',
        ]);

        /** @var Collection<int, array{external_id: string, title: string, url: string, source_ref: string, body: string, published_at: CarbonInterface}> $entries */
        $entries = collect();

        foreach ($xml->entry as $entry) {
            $entries->push([
                'external_id'  => trim((string)$entry->id),
                'title'        => trim((string)$entry->title),
                'url'          => $this->alternateLink($entry),
                'source_ref'   => $repo,
                'body'         => trim($converter->convert((string)$entry->content)),
                'published_at' => Carbon::parse($this->timestamp($entry)),
            ]);
        }

        return $entries;
    }

    private function alternateLink(SimpleXMLElement $entry): string
    {
        foreach ($entry->link as $link) {
            $attributes = $link->attributes();

            if ($attributes !== null && (string)$attributes['rel'] === 'alternate') {
                return (string)$attributes['href'];
            }
        }

        return '';
    }

    private function timestamp(SimpleXMLElement $entry): string
    {
        $published = trim((string)$entry->published);

        return $published !== '' ? $published : trim((string)$entry->updated);
    }
}

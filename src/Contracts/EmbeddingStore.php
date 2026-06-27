<?php

namespace Arzcode\Sisifo\Contracts;

/**
 * @internal DEFERRED: the embedding store is scaffolding for the broader Sisifo
 * memory feature. It is not yet wired into mailbox tasks.
 */
interface EmbeddingStore
{
    /**
     * Store an embedding vector for a piece of content.
     *
     * @param  array<int, float>  $embedding
     */
    public function store(int $clientId, string $content, array $embedding): int;

    /**
     * Search for the N closest embeddings for a given client.
     *
     * @param  array<int, float>  $embedding
     * @return array<int, array{id: int, content: string, distance: float}>
     */
    public function search(int $clientId, array $embedding, int $limit = 10): array;
}

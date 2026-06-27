<?php

namespace Arzcode\Sisifo\Embeddings\Drivers;

use Arzcode\Sisifo\Contracts\EmbeddingStore;
use Illuminate\Support\Facades\DB;

/**
 * @internal DEFERRED: not yet wired into mailbox tasks. Scaffolding for future
 * memory features per sisifo.md §2. Uses MariaDB 11.8+ native VECTOR with HNSW.
 */
class MariaDbVectorStore implements EmbeddingStore
{
    public function store(int $clientId, string $content, array $embedding): int
    {
        $json = json_encode($embedding);

        DB::statement(
            'INSERT INTO sisifo_memories (client_id, content, embedding, created_at, updated_at)
             VALUES (?, ?, VEC_FromText(?), NOW(), NOW())',
            [$clientId, $content, $json]
        );

        return (int)DB::getPdo()->lastInsertId();
    }

    public function search(int $clientId, array $embedding, int $limit = 10): array
    {
        $json = json_encode($embedding);

        $rows = DB::select(
            'SELECT id, content, VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS distance
             FROM sisifo_memories
             WHERE client_id = ?
             ORDER BY distance ASC
             LIMIT ?',
            [$json, $clientId, $limit]
        );

        return array_map(fn($row) => [
            'id'       => (int)$row->id,
            'content'  => $row->content,
            'distance' => (float)$row->distance,
        ], $rows);
    }
}

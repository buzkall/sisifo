<?php

namespace Arzcode\Sisifo\Embeddings\Drivers;

use Arzcode\Sisifo\Contracts\EmbeddingStore;
use Illuminate\Support\Facades\DB;

/**
 * @internal DEFERRED: not yet wired into mailbox tasks. Scaffolding for future
 * memory features per sisifo.md §2.
 */
class PgVectorStore implements EmbeddingStore
{
    public function store(int $clientId, string $content, array $embedding): int
    {
        $vector = '[' . implode(',', $embedding) . ']';

        return (int)DB::table('sisifo_memories')->insertGetId([
            'client_id'  => $clientId,
            'content'    => $content,
            'embedding'  => $vector,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function search(int $clientId, array $embedding, int $limit = 10): array
    {
        $vector = '[' . implode(',', $embedding) . ']';

        $rows = DB::select(
            'SELECT id, content, embedding <=> ?::vector AS distance
             FROM sisifo_memories
             WHERE client_id = ?
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$vector, $clientId, $vector, $limit]
        );

        return array_map(fn($row) => [
            'id'       => (int)$row->id,
            'content'  => $row->content,
            'distance' => (float)$row->distance,
        ], $rows);
    }
}

<?php

namespace Arzcode\Sisifo\Embeddings\Drivers;

use Arzcode\Sisifo\Contracts\EmbeddingStore;
use Illuminate\Support\Facades\DB;

/**
 * @internal DEFERRED: not yet wired into mailbox tasks. Scaffolding for future
 * memory features per sisifo.md §2.
 *
 * Brute-force store for MySQL Community (no native distance functions). Loads
 * all rows for the tenant into PHP and computes cosine distance in-process.
 * Adequate up to a few thousand rows per client.
 */
class MysqlBruteForceStore implements EmbeddingStore
{
    public function store(int $clientId, string $content, array $embedding): int
    {
        $json = json_encode($embedding);

        return (int)DB::table('sisifo_memories')->insertGetId([
            'client_id'  => $clientId,
            'content'    => $content,
            'embedding'  => $json,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function search(int $clientId, array $embedding, int $limit = 10): array
    {
        $rows = DB::table('sisifo_memories')
            ->select(['id', 'content', 'embedding'])
            ->where('client_id', $clientId)
            ->get();

        $scored = $rows->map(function($row) use ($embedding) {
            $stored = is_string($row->embedding) ? json_decode($row->embedding, true) : [];

            return [
                'id'       => (int)$row->id,
                'content'  => $row->content,
                'distance' => $this->cosineDistance($embedding, $stored ?? []),
            ];
        })->sortBy('distance')->take($limit)->values()->all();

        return $scored;
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineDistance(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 1.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        foreach ($a as $i => $av) {
            $bv = $b[$i];
            $dot += $av * $bv;
            $na += $av * $av;
            $nb += $bv * $bv;
        }

        if ($na === 0.0 || $nb === 0.0) {
            return 1.0;
        }

        return 1.0 - ($dot / (sqrt($na) * sqrt($nb)));
    }
}

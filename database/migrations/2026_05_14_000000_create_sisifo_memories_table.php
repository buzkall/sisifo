<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('sisifo_memories', function(Blueprint $table) use ($driver) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->text('content');
            if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
                $table->fullText('content');
            }
            $table->timestamps();
        });

        $dim = (int)config('sisifo.embeddings.dimensions', 1536);

        match ($driver) {
            'pgsql' => DB::unprepared("
                CREATE EXTENSION IF NOT EXISTS vector;
                ALTER TABLE sisifo_memories ADD COLUMN embedding vector({$dim});
                CREATE INDEX ON sisifo_memories USING hnsw (embedding vector_cosine_ops);
            "),
            'mariadb' => DB::statement("
                ALTER TABLE sisifo_memories
                    ADD COLUMN embedding VECTOR({$dim}) NOT NULL,
                    ADD VECTOR INDEX (embedding) DISTANCE=cosine
            "),
            'mysql' => DB::statement('
                ALTER TABLE sisifo_memories ADD COLUMN embedding JSON NULL
            '),
            default => null,
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('sisifo_memories');
    }
};

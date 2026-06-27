<?php

use Arzcode\Sisifo\Contracts\EmbeddingStore;
use Arzcode\Sisifo\Embeddings\Drivers\MariaDbVectorStore;
use Arzcode\Sisifo\Embeddings\Drivers\MysqlBruteForceStore;
use Arzcode\Sisifo\Embeddings\Drivers\PgVectorStore;
use Illuminate\Support\Facades\DB;

it('binds the MysqlBruteForceStore for sqlite (default fallback)', function() {
    expect(app(EmbeddingStore::class))->toBeInstanceOf(MysqlBruteForceStore::class);
});

it('binds the PgVectorStore when the connection is pgsql', function() {
    DB::shouldReceive('connection')->andReturn(new class
    {
        public function getDriverName(): string
        {
            return 'pgsql';
        }
    });

    expect(app()->make(EmbeddingStore::class))->toBeInstanceOf(PgVectorStore::class);
});

it('binds the MariaDbVectorStore when the connection is mariadb', function() {
    DB::shouldReceive('connection')->andReturn(new class
    {
        public function getDriverName(): string
        {
            return 'mariadb';
        }
    });

    expect(app()->make(EmbeddingStore::class))->toBeInstanceOf(MariaDbVectorStore::class);
});

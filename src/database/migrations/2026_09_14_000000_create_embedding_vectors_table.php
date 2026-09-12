<?php

use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Migration\Migration;

return new class extends Migration
{
    /**
     * Run the migrations
     *
     * This table backs the "pgvector" driver only. It uses a native
     * PostgreSQL `vector` column and an HNSW index, so it cannot be
     * expressed through the regular cross-database Blueprint — it is
     * only ever created when you have actually chosen that driver.
     *
     * @return void
     */
    public function up(): void
    {
        if (config('embeds.driver') !== 'pgvector') {
            return;
        }

        $connectionName = config('embeds.connections.pgvector');
        $pdo = DB::getPdoInstance($connectionName);

        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            throw new \RuntimeException(
                'The "pgvector" embeds driver requires a PostgreSQL connection. '
                    . 'Set EMBEDS_PGVECTOR_CONNECTION to a pgsql connection, or switch '
                    . 'EMBEDS_DRIVER back to "brute_force".'
            );
        }

        $dimensions = (int) config('embeds.dimensions', 384);

        DB::connection($connectionName)->statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::connection($connectionName)->statement("
            CREATE TABLE IF NOT EXISTS embedding_vectors (
                id BIGSERIAL PRIMARY KEY,
                embeddable_type VARCHAR(255) NOT NULL,
                embeddable_id VARCHAR(255) NOT NULL,
                attribute VARCHAR(255) NOT NULL,
                vector vector({$dimensions}) NOT NULL,
                created_at TIMESTAMP DEFAULT now(),
                updated_at TIMESTAMP DEFAULT now(),
                UNIQUE (embeddable_type, embeddable_id, attribute)
            )
        ");

        DB::connection($connectionName)->statement('
            CREATE INDEX IF NOT EXISTS embedding_vectors_hnsw_idx
                ON embedding_vectors USING hnsw (vector vector_cosine_ops)
        ');
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        if (config('embeds.driver') !== 'pgvector') {
            return;
        }

        $connectionName = config('embeds.connections.pgvector');

        DB::connection($connectionName)->statement('DROP TABLE IF EXISTS embedding_vectors');
    }
};

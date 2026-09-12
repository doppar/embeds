<?php

namespace Doppar\Embeds\Drivers;

use Doppar\Embeds\Contracts\VectorIndexDriver;
use Phaseolies\Support\Facades\DB;

class PgVectorDriver implements VectorIndexDriver
{
    /**
     * Store (or replace) the vector for one model's embedded attribute.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param int|string $id
     * @param array<int, float> $vector
     * @return void
     */
    public function index(string $modelClass, string $attribute, int|string $id, array $vector): void
    {
        DB::connection($this->connection())->execute(
            'INSERT INTO embedding_vectors (embeddable_type, embeddable_id, attribute, vector, updated_at)
             VALUES (?, ?, ?, ?::vector, now())
             ON CONFLICT (embeddable_type, embeddable_id, attribute)
             DO UPDATE SET vector = EXCLUDED.vector, updated_at = now()',
            [$modelClass, (string) $id, $attribute, $this->toVectorLiteral($vector)]
        );
    }

    /**
     * Remove a stored vector, e.g. when the embedded column is cleared.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param int|string $id
     * @return void
     */
    public function forget(string $modelClass, string $attribute, int|string $id): void
    {
        DB::connection($this->connection())->execute(
            'DELETE FROM embedding_vectors
             WHERE embeddable_type = ? AND embeddable_id = ? AND attribute = ?',
            [$modelClass, (string) $id, $attribute]
        );
    }

    /**
     * Run a nearest-neighbour search using pgvector's `<=>` cosine-distance
     * operator, returning the closest matches' ids — Postgres does the
     * ranking, not PHP.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param array<int, float> $queryVector
     * @param int $limit
     * @return list<string>
     */
    public function search(string $modelClass, string $attribute, array $queryVector, int $limit): array
    {
        $rows = DB::connection($this->connection())->query(
            'SELECT embeddable_id FROM embedding_vectors
             WHERE embeddable_type = ? AND attribute = ?
             ORDER BY vector <=> ?::vector
             LIMIT ?',
            [$modelClass, $attribute, $this->toVectorLiteral($queryVector), $limit]
        );

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (string) $row->embeddable_id;
        }

        return $ids;
    }

    /**
     * Render a vector as the bracketed text literal pgvector accepts for
     * an `::vector` cast, e.g. "[0.1,-0.2,0.3]".
     *
     * @param array<int, float> $vector
     * @return string
     */
    protected function toVectorLiteral(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }

    /**
     * The database connection this driver should use, or null to fall
     * back to the application's default connection.
     *
     * @return string|null
     */
    protected function connection(): ?string
    {
        return config('embeds.connections.pgvector');
    }
}

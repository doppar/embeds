<?php

namespace Doppar\Embeds\Drivers;

use Doppar\Embeds\Embedding;
use Doppar\Embeds\Vector;
use Doppar\Embeds\Contracts\VectorIndexDriver;

class BruteForceDriver implements VectorIndexDriver
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
        Embedding::updateOrCreate(
            [
                'embeddable_type' => $modelClass,
                'embeddable_id' => (string) $id,
                'attribute' => $attribute,
            ],
            [
                'vector' => json_encode($vector),
            ]
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
        Embedding::where('embeddable_type', $modelClass)
            ->where('embeddable_id', (string) $id)
            ->where('attribute', $attribute)
            ->delete();
    }

    /**
     * Rank every stored vector for this model class and attribute
     *
     * @param string $modelClass
     * @param string $attribute
     * @param array<int, float> $queryVector
     * @param int $limit
     * @return list<string>
     */
    public function search(string $modelClass, string $attribute, array $queryVector, int $limit): array
    {
        $candidates = $this->allVectorsFor($modelClass, $attribute);

        if (empty($candidates)) {
            return [];
        }

        return array_map('strval', Vector::rank($candidates, $queryVector, $limit));
    }

    /**
     * Load every stored vector for a model class and attribute, keyed by the owning record's id
     *
     * @param string $modelClass
     * @param string $attribute
     * @return array<string, array<int, float>>
     */
    protected function allVectorsFor(string $modelClass, string $attribute): array
    {
        $rows = Embedding::where('embeddable_type', $modelClass)
            ->where('attribute', $attribute)
            ->get();

        $vectors = [];

        foreach ($rows as $row) {
            $vectors[$row->embeddable_id] = json_decode($row->vector, true) ?? [];
        }

        return $vectors;
    }
}

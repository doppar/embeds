<?php

namespace Doppar\Embeds\Contracts;

/**
 * How #[Embeds] vectors are stored and searched. Every driver — brute
 * force, pgvector, redis — implements this same contract, so switching
 * drivers is a config change: #[Embeds] and whereSimilarTo() never change.
 */
interface VectorIndexDriver
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
    public function index(string $modelClass, string $attribute, int|string $id, array $vector): void;

    /**
     * Remove a stored vector, e.g. when the embedded column is cleared.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param int|string $id
     * @return void
     */
    public function forget(string $modelClass, string $attribute, int|string $id): void;

    /**
     * Find the ids of the records whose stored vector for this model class
     * and attribute is most similar to the given query vector.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param array<int, float> $queryVector
     * @param int $limit
     * @return list<string> Ranked embeddable ids, most similar first.
     */
    public function search(string $modelClass, string $attribute, array $queryVector, int $limit): array;
}

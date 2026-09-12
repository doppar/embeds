<?php

namespace Doppar\Embeds;

class Vector
{
    /**
     * Compute the cosine similarity between two equal-length numeric vectors.
     *
     * Returns a value in roughly [-1, 1] — 1 means identical direction
     * (maximally similar), 0 means unrelated, -1 means opposite.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = $b[$i] ?? 0.0;

            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        $magnitude = sqrt($normA) * sqrt($normB);

        if ($magnitude === 0.0) {
            return 0.0;
        }

        return $dot / $magnitude;
    }

    /**
     * Flatten a nested pipeline result (e.g. [[0.1, 0.2, ...]]) down to a
     * single flat vector of floats.
     *
     * @param mixed $result
     * @return array<int, float>
     */
    public static function flatten(mixed $result): array
    {
        while (is_array($result) && is_array($result[0] ?? null)) {
            $result = $result[0];
        }

        return is_array($result) ? array_values($result) : [];
    }

    /**
     * Rank a set of [key => vector] candidates against a query vector,
     * returning keys sorted by descending similarity.
     *
     * @param array<int|string, array<int, float>> $candidates
     * @param array<int, float> $query
     * @param int $limit
     * @return list<int|string>
     */
    public static function rank(array $candidates, array $query, int $limit): array
    {
        $scored = [];

        foreach ($candidates as $key => $vector) {
            $scored[] = [$key, self::cosineSimilarity($vector, $query)];
        }

        usort($scored, fn($a, $b) => $b[1] <=> $a[1]);

        return array_map(
            fn($entry) => $entry[0],
            array_slice($scored, 0, $limit)
        );
    }
}

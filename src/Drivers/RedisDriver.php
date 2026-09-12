<?php

namespace Doppar\Embeds\Drivers;

use Doppar\Embeds\Contracts\VectorIndexDriver;
use Predis\Client;
use Predis\Command\RawCommand;
use Predis\Response\ServerException;

class RedisDriver implements VectorIndexDriver
{
    private const INDEX_NAME = 'embeds_idx';
    private const KEY_PREFIX = 'embeds:';

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var bool
     */
    private static bool $indexEnsured = false;

    /**
     * Connect to Redis, using the given connection options or falling back
     * to the "redis" entry in the embeds config.
     *
     * @param array<string, mixed>|null $connection
     */
    public function __construct(?array $connection = null)
    {
        if (!class_exists(Client::class)) {
            throw new \RuntimeException(
                'The "redis" embeds driver requires predis/predis. Install it with: composer require predis/predis'
            );
        }

        $this->client = new Client($connection ?? config('embeds.connections.redis', [
            'host' => '127.0.0.1',
            'port' => 6379,
        ]));
    }

    /**
     * Store (or replace) the vector for one model's embedded attribute, as
     * a Redis hash the RediSearch index can pick up.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param int|string $id
     * @param array<int, float> $vector
     * @return void
     */
    public function index(string $modelClass, string $attribute, int|string $id, array $vector): void
    {
        $this->ensureIndex(count($vector));

        $this->client->hset(
            $this->key($modelClass, $attribute, $id),
            'embeddable_type',
            $modelClass,
        );
        $this->client->hset(
            $this->key($modelClass, $attribute, $id),
            'attribute',
            $attribute,
        );
        $this->client->hset(
            $this->key($modelClass, $attribute, $id),
            'embeddable_id',
            (string) $id,
        );
        $this->client->hset(
            $this->key($modelClass, $attribute, $id),
            'vector',
            $this->packVector($vector),
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
        $this->client->del([$this->key($modelClass, $attribute, $id)]);
    }

    /**
     * Run a KNN similarity search inside Redis via RediSearch's vector
     * field type, returning the closest matches' ids — Redis does the
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
        $this->ensureIndex(count($queryVector));

        $query = sprintf(
            '(@embeddable_type:{%s} @attribute:{%s})=>[KNN %d @vector $vec AS score]',
            $this->escapeTag($modelClass),
            $this->escapeTag($attribute),
            $limit,
        );

        $result = $this->client->executeCommand(RawCommand::create(
            'FT.SEARCH',
            self::INDEX_NAME,
            $query,
            'PARAMS',
            '2',
            'vec',
            $this->packVector($queryVector),
            'SORTBY',
            'score',
            'RETURN',
            '1',
            'embeddable_id',
            'DIALECT',
            '2',
        ));

        return $this->parseSearchResults($result);
    }

    /**
     * FT.SEARCH replies as [count, key1, fields1, key2, fields2, ...] where
     * each "fields" entry is itself a flat [name, value, ...] list.
     *
     * @param array<int, mixed> $result
     * @return list<string>
     */
    protected function parseSearchResults(array $result): array
    {
        $ids = [];
        $total = array_shift($result) ?? 0;

        for ($i = 0; $i < (int) $total && !empty($result); $i++) {
            array_shift($result); // the Redis key itself, unused
            $fields = array_shift($result) ?? [];

            for ($f = 0; $f < count($fields); $f += 2) {
                if (($fields[$f] ?? null) === 'embeddable_id') {
                    $ids[] = (string) $fields[$f + 1];
                }
            }
        }

        return $ids;
    }

    /**
     * Create the RediSearch index on first use. Safe to call repeatedly —
     * an "Index already exists" error from Redis is swallowed.
     *
     * @param int $dimensions
     * @return void
     */
    protected function ensureIndex(int $dimensions): void
    {
        if (self::$indexEnsured) {
            return;
        }

        try {
            $this->client->executeCommand(RawCommand::create(
                'FT.CREATE',
                self::INDEX_NAME,
                'ON',
                'HASH',
                'PREFIX',
                '1',
                self::KEY_PREFIX,
                'SCHEMA',
                'embeddable_type',
                'TAG',
                'attribute',
                'TAG',
                'embeddable_id',
                'TAG',
                'vector',
                'VECTOR',
                'HNSW',
                '6',
                'TYPE',
                'FLOAT32',
                'DIM',
                (string) $dimensions,
                'DISTANCE_METRIC',
                'COSINE',
            ));
        } catch (ServerException $e) {
            if (!str_contains($e->getMessage(), 'Index already exists')) {
                throw $e;
            }
        }

        self::$indexEnsured = true;
    }

    /**
     * Build the Redis hash key for one model's embedded attribute — also
     * the prefix RediSearch is told to index in FT.CREATE.
     *
     * @param string $modelClass
     * @param string $attribute
     * @param int|string $id
     * @return string
     */
    protected function key(string $modelClass, string $attribute, int|string $id): string
    {
        return self::KEY_PREFIX . "{$modelClass}:{$attribute}:{$id}";
    }

    /**
     * Pack a vector into the raw little-endian FLOAT32 bytes RediSearch
     * expects for a VECTOR field.
     *
     * @param array<int, float> $vector
     * @return string
     */
    protected function packVector(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /**
     * Escape characters RediSearch treats as special inside a TAG query.
     *
     * @param string $value
     * @return string
     */
    protected function escapeTag(string $value): string
    {
        return preg_replace('/([,.<>{}\[\]"\':;!@#$%^&*()\-+=~\/\\\\ ])/', '\\\\$1', $value) ?? $value;
    }
}

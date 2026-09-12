<?php

namespace Doppar\Embeds\Tests;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Command\RawCommand;
use Doppar\Embeds\Drivers\RedisDriver;

/**
 * Exercises RedisDriver against a real Redis connection. Requires the
 * RediSearch module (Redis Stack) to actually be loaded — if it is not,
 * every test here skips with a clear reason rather than failing, the same
 * way PgVectorDriverTest skips when pgvector is unavailable.
 */
class RedisDriverTest extends TestCase
{
    private ?Client $client = null;

    /**
     * True only once setUp() has a live, RediSearch-capable connection —
     * guards tearDown() so a failed connect() (client object already
     * assigned, but never actually connected) can't masquerade as a real
     * test error when it tries to clean up.
     *
     * @var bool
     */
    private bool $ready = false;

    protected function setUp(): void
    {
        try {
            $this->client = new Client([
                'host' => getenv('EMBEDS_TEST_REDIS_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('EMBEDS_TEST_REDIS_PORT') ?: 6379),
            ]);
            $this->client->connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No local Redis server reachable: ' . $e->getMessage());
        }

        $modules = (array) $this->client->executeCommand(RawCommand::create('MODULE', 'LIST'));

        if (!$this->hasSearchModule($modules)) {
            $this->markTestSkipped('The RediSearch module (Redis Stack) is not loaded on this Redis server.');
        }

        $this->ready = true;
        $this->dropIndexIfExists();
        $this->flushEmbedsKeys();
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            $this->dropIndexIfExists();
            $this->flushEmbedsKeys();
        }
    }

    public function testIndexAndSearchRankByRealCosineDistance(): void
    {
        $driver = $this->makeDriver();

        $driver->index('App\\Models\\Product', 'description', 1, [1.0, 0.0, 0.0]);
        $driver->index('App\\Models\\Product', 'description', 2, [0.9, 0.1, 0.0]);
        $driver->index('App\\Models\\Product', 'description', 3, [0.0, 1.0, 0.0]);

        $ranked = $driver->search('App\\Models\\Product', 'description', [1.0, 0.0, 0.0], 2);

        $this->assertSame(['1', '2'], $ranked);
    }

    public function testForgetRemovesTheStoredVector(): void
    {
        $driver = $this->makeDriver();

        $driver->index('App\\Models\\Product', 'description', 1, [1.0, 0.0, 0.0]);
        $driver->forget('App\\Models\\Product', 'description', 1);

        $ranked = $driver->search('App\\Models\\Product', 'description', [1.0, 0.0, 0.0], 10);

        $this->assertSame([], $ranked);
    }

    private function makeDriver(): RedisDriver
    {
        return new RedisDriver([
            'host' => getenv('EMBEDS_TEST_REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('EMBEDS_TEST_REDIS_PORT') ?: 6379),
        ]);
    }

    private function hasSearchModule(array $modules): bool
    {
        foreach ($modules as $module) {
            $fields = (array) $module;

            foreach ($fields as $i => $value) {
                if ($value === 'name' && strtolower((string) ($fields[$i + 1] ?? '')) === 'search') {
                    return true;
                }
            }
        }

        return false;
    }

    private function dropIndexIfExists(): void
    {
        try {
            $this->client->executeCommand(RawCommand::create('FT.DROPINDEX', 'embeds_idx'));
        } catch (\Throwable) {
            // no index yet, nothing to drop
        }
    }

    private function flushEmbedsKeys(): void
    {
        $keys = $this->client->keys('embeds:*');

        if (!empty($keys)) {
            $this->client->del($keys);
        }
    }
}

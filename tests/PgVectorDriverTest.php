<?php

namespace Doppar\Embeds\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\LoggerService;
use Phaseolies\Support\UrlGenerator;
use Doppar\Embeds\Drivers\PgVectorDriver;
use Doppar\Embeds\Tests\Support\TestContainer;

/**
 * Exercises PgVectorDriver against a real PostgreSQL connection. Requires
 * the pgvector extension to actually be installed on the server — if it
 * is not, every test here skips with a clear reason rather than failing,
 * the same way TransformersTest skips when a model can't be loaded.
 */
class PgVectorDriverTest extends TestCase
{
    private const CONNECTION = 'embeds_pgvector_test';

    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        Container::setInstance(new TestContainer());

        $container = Container::getInstance();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->singleton('log', LoggerService::class);

        try {
            $this->pdo = new PDO('pgsql:host=' . $this->socketDir() . ';dbname=postgres');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable $e) {
            $this->markTestSkipped('No local PostgreSQL server reachable: ' . $e->getMessage());
        }

        try {
            $this->pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            $this->markTestSkipped('pgvector extension is not installed on this PostgreSQL server: ' . $e->getMessage());
        }

        $this->pdo->exec('DROP TABLE IF EXISTS embedding_vectors');
        $this->pdo->exec("
            CREATE TABLE embedding_vectors (
                id BIGSERIAL PRIMARY KEY,
                embeddable_type VARCHAR(255) NOT NULL,
                embeddable_id VARCHAR(255) NOT NULL,
                attribute VARCHAR(255) NOT NULL,
                vector vector(3) NOT NULL,
                created_at TIMESTAMP DEFAULT now(),
                updated_at TIMESTAMP DEFAULT now(),
                UNIQUE (embeddable_type, embeddable_id, attribute)
            )
        ");
        $this->pdo->exec('
            CREATE INDEX embedding_vectors_hnsw_idx
                ON embedding_vectors USING hnsw (vector vector_cosine_ops)
        ');

        $this->setStaticProperty(Database::class, 'connections', [
            self::CONNECTION => $this->pdo,
        ]);

        config([
            'embeds.driver' => 'pgvector',
            'embeds.connections.pgvector' => self::CONNECTION,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('DROP TABLE IF EXISTS embedding_vectors');
        }

        $this->setStaticProperty(Database::class, 'connections', []);
    }

    public function testIndexAndSearchRankByRealCosineDistance(): void
    {
        $driver = new PgVectorDriver();

        $driver->index('App\\Models\\Product', 'description', 1, [1.0, 0.0, 0.0]);
        $driver->index('App\\Models\\Product', 'description', 2, [0.9, 0.1, 0.0]);
        $driver->index('App\\Models\\Product', 'description', 3, [0.0, 1.0, 0.0]);

        $ranked = $driver->search('App\\Models\\Product', 'description', [1.0, 0.0, 0.0], 2);

        $this->assertSame(['1', '2'], $ranked);
    }

    public function testIndexIsIdempotentPerAttribute(): void
    {
        $driver = new PgVectorDriver();

        $driver->index('App\\Models\\Product', 'description', 1, [1.0, 0.0, 0.0]);
        $driver->index('App\\Models\\Product', 'description', 1, [0.0, 1.0, 0.0]);

        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM embedding_vectors WHERE embeddable_id = '1'")
            ->fetchColumn();

        $this->assertSame(1, $count);
    }

    public function testForgetRemovesTheStoredVector(): void
    {
        $driver = new PgVectorDriver();

        $driver->index('App\\Models\\Product', 'description', 1, [1.0, 0.0, 0.0]);
        $driver->forget('App\\Models\\Product', 'description', 1);

        $ranked = $driver->search('App\\Models\\Product', 'description', [1.0, 0.0, 0.0], 10);

        $this->assertSame([], $ranked);
    }

    private function socketDir(): string
    {
        foreach (['/var/run/postgresql', '/tmp'] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return '/var/run/postgresql';
    }

    private function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, $value);
    }
}

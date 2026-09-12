<?php

namespace Doppar\Embeds\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\LoggerService;
use Phaseolies\Support\UrlGenerator;
use Doppar\Embeds\Embedding;
use Doppar\Embeds\Tests\Support\TestContainer;
use Doppar\Embeds\Tests\Support\Models\TestProduct;

class EmbedsIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new TestContainer());

        $container = Container::getInstance();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->singleton('log', LoggerService::class);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite' => $this->pdo,
        ]);
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    public function testSavingAnEmbeddedPropertyPersistsAVector(): void
    {
        $product = new TestProduct();
        $product->name = 'Trail Pack 40L';
        $product->description = 'a durable waterproof backpack';
        $product->save();

        $rows = Embedding::where('embeddable_type', TestProduct::class)
            ->where('embeddable_id', (string) $product->getKey())
            ->where('attribute', 'description')
            ->get();

        $this->assertCount(1, $rows);

        $vector = json_decode($rows->first()->vector, true);

        $this->assertIsArray($vector);
        $this->assertCount(384, $vector);
    }

    public function testClearingAnEmbeddedPropertyRemovesTheStoredVector(): void
    {
        $product = new TestProduct();
        $product->name = 'Trail Pack 40L';
        $product->description = 'a durable waterproof backpack';
        $product->save();

        $product->description = '';
        $product->save();

        $rows = Embedding::where('embeddable_type', TestProduct::class)
            ->where('embeddable_id', (string) $product->getKey())
            ->where('attribute', 'description')
            ->get();

        $this->assertCount(0, $rows);
    }

    public function testWhereSimilarToRanksSemanticallyRelatedTextAboveUnrelatedText(): void
    {
        $backpack = new TestProduct();
        $backpack->name = 'Trail Pack 40L';
        $backpack->description = 'rugged daypack for hiking in the rain';
        $backpack->save();

        $cookie = new TestProduct();
        $cookie->name = 'Recipe Card';
        $cookie->description = 'chocolate chip cookie recipe';
        $cookie->save();

        $results = TestProduct::whereSimilarTo('description', 'a durable waterproof backpack', 5);

        $this->assertGreaterThan(0, $results->count());
        $this->assertSame($backpack->getKey(), $results->first()->getKey());
    }

    private function createTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                description TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $this->pdo->exec("
            CREATE TABLE embeddings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                embeddable_type TEXT NOT NULL,
                embeddable_id TEXT NOT NULL,
                attribute TEXT NOT NULL,
                vector TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )
        ");
    }

    private function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, $value);
    }
}

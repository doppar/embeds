<?php

namespace Doppar\Embeds;

use Doppar\AI\Pipeline;
use Doppar\AI\Enum\TaskEnum;
use Doppar\Embeds\Attributes\Embeds;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Watches\WatchesHandler;
use Phaseolies\Support\Collection;

trait InteractsWithEmbeddings
{
    /**
     * Per-class guard so the #[Embeds] scan only runs once per model class.
     *
     * @var array<string, bool>
     */
    private static array $embedsBooted = [];

    /**
     * Per-class cache of the property names carrying #[Embeds], so a fresh
     * INSERT (which #[Watches] never fires for) can still be embedded once,
     * right after the row gets its primary key.
     *
     * @var array<string, list<string>>
     */
    private static array $embedsProperties = [];

    /**
     * Scans for #[Embeds] properties before deferring to the real Model constructor
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->registerEmbedsAttributes();

        parent::__construct($attributes);
    }

    /**
     * #[Watches] (and therefore #[Embeds], which is built on top of it)
     * only fires on the UPDATE path of save() — a brand new row never
     * triggers it, since there is no "change" to react to yet. Without
     * this override, a model created with its embedded text already set
     * would silently never get an embedding until its first update.
     *
     * @return bool
     */
    public function save(): bool
    {
        $isCreate = $this->getKey() === null;

        $result = parent::save();

        if ($result && $isCreate) {
            $this->fireEmbedsForNewRecord();
        }

        return $result;
    }

    /**
     * Compute an embedding for every #[Embeds] property that already has a
     * value, immediately after this record's first successful insert.
     *
     * @return void
     */
    protected function fireEmbedsForNewRecord(): void
    {
        foreach (self::$embedsProperties[static::class] ?? [] as $property) {
            $value = $this->attributes[$property] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            app("embeds::" . static::class . "::{$property}")->handle(null, $value, $this);
        }
    }

    /**
     * Scan #[Embeds] attributes on this model's properties and register a
     * dedicated watcher for each one, reusing the existing #[Watches]
     * lifecycle so no framework changes are needed to fire on update().
     *
     * @return void
     */
    protected function registerEmbedsAttributes(): void
    {
        $class = static::class;

        if (isset(self::$embedsBooted[$class])) {
            return;
        }

        self::$embedsBooted[$class] = true;
        self::$embedsProperties[$class] = [];

        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Embeds::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Embeds $embeds */
            $embeds = $attributes[0]->newInstance();
            $name = $property->getName();
            $watcherKey = "embeds::{$class}::{$name}";

            app()->singleton($watcherKey, fn() => new EmbeddingWatcher(
                EmbedsManager::driver(),
                $name,
                $embeds->model,
            ));

            WatchesHandler::register($class, $name, $watcherKey);
            self::$embedsProperties[$class][] = $name;
        }
    }

    /**
     * Find records whose embedded column is semantically similar to the
     * given text, ranked by similarity (most similar first).
     *
     * Ranking is delegated entirely to the configured driver: the
     * brute-force driver ranks candidates in PHP, while a real index like
     * "pgvector" ranks them inside the database via its own similarity
     * operator — this method does not know or care which.
     *
     * @param Builder $builder
     * @param string $column
     * @param string $text
     * @param int $limit
     * @param string|null $model
     * @return Collection
     */
    public function __whereSimilarTo(
        Builder $builder,
        string $column,
        string $text,
        int $limit = 10,
        ?string $model = null,
    ): Collection {
        $modelInstance = $builder->getModel();
        $modelClass = get_class($modelInstance);

        $queryVector = Vector::flatten(
            Pipeline::execute(task: TaskEnum::EMBEDDING, data: $text, model: $model)
        );

        $rankedIds = EmbedsManager::driver()->search($modelClass, $column, $queryVector, $limit);

        if (empty($rankedIds)) {
            return new Collection($modelClass, []);
        }

        $results = $builder
            ->whereIn($modelInstance->getKeyName(), $rankedIds)
            ->get();

        $byId = [];
        foreach ($results as $result) {
            $byId[(string) $result->getKey()] = $result;
        }

        $ordered = [];
        foreach ($rankedIds as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return new Collection($modelClass, $ordered);
    }
}

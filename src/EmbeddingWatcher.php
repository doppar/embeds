<?php

namespace Doppar\Embeds;

use Doppar\AI\Pipeline;
use Doppar\AI\Enum\TaskEnum;
use Doppar\Embeds\Contracts\VectorIndexDriver;
use Phaseolies\Database\Entity\Model;

class EmbeddingWatcher
{
    /**
     * @param VectorIndexDriver $driver
     * @param string $attribute
     * @param string|null $embeddingModel
     */
    public function __construct(
        private readonly VectorIndexDriver $driver,
        private readonly string $attribute,
        private readonly ?string $embeddingModel = null,
    ) {}

    /**
     * Compute and persist (or clear) the embedding for the changed attribute.
     *
     * @param mixed $old
     * @param mixed $new
     * @param Model $model
     * @return void
     */
    public function handle(mixed $old, mixed $new, Model $model): void
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        if ($modelId === null) {
            return;
        }

        $text = trim((string) $new);

        if ($text === '') {
            $this->driver->forget($modelClass, $this->attribute, $modelId);

            return;
        }

        $result = Pipeline::execute(
            task: TaskEnum::EMBEDDING,
            data: $text,
            model: $this->embeddingModel,
        );

        $vector = Vector::flatten($result);

        $this->driver->index($modelClass, $this->attribute, $modelId, $vector);
    }
}

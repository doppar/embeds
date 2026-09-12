<?php

namespace Doppar\Embeds\Attributes;

/**
 * Marks a model property as semantically searchable. A property carrying
 * this attribute has its value embedded and kept in sync automatically by
 * InteractsWithEmbeddings, and becomes queryable via Model::whereSimilarTo().
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Embeds
{
    /**
     * @param string|null $model The local transformers model to use for this
     * column. Defaults to Xenova/all-MiniLM-L6-v2.
     */
    public function __construct(
        public readonly ?string $model = null,
    ) {}
}

<?php

namespace Doppar\Embeds\Attributes;

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

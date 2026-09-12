<?php

namespace Doppar\Embeds;

use Phaseolies\Database\Entity\Model;

class Embedding extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'embeddings';

    /**
     * The attributes that are mass creatable.
     *
     * @var array<int, string>
     */
    protected $creatable = [
        'embeddable_type',
        'embeddable_id',
        'attribute',
        'vector',
    ];
}

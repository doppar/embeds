<?php

namespace Doppar\Embeds\Tests\Support\Models;

use Doppar\Embeds\Attributes\Embeds;
use Doppar\Embeds\InteractsWithEmbeddings;
use Phaseolies\Database\Entity\Model;

class TestProduct extends Model
{
    use InteractsWithEmbeddings;

    protected $table = 'test_products';

    protected $creatable = ['name', 'description'];

    #[Embeds]
    protected $description;
}

<?php

namespace Doppar\Embeds\Tests\Support\Models;

use Doppar\Embeds\Attributes\Embeds;
use Doppar\Embeds\Concerns\Embeddable;
use Phaseolies\Database\Entity\Model;

class TestProduct extends Model
{
    use Embeddable;

    protected $table = 'test_products';

    protected $creatable = ['name', 'description'];

    #[Embeds]
    protected $description;
}

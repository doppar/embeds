<?php

namespace Doppar\Embeds\Tests;

use Doppar\Embeds\Vector;
use PHPUnit\Framework\TestCase;

class VectorTest extends TestCase
{
    public function testIdenticalVectorsHaveSimilarityOfOne(): void
    {
        $vector = [1.0, 2.0, 3.0];

        $this->assertEqualsWithDelta(1.0, Vector::cosineSimilarity($vector, $vector), 0.0001);
    }

    public function testOrthogonalVectorsHaveSimilarityOfZero(): void
    {
        $this->assertEqualsWithDelta(0.0, Vector::cosineSimilarity([1.0, 0.0], [0.0, 1.0]), 0.0001);
    }

    public function testOppositeVectorsHaveSimilarityOfNegativeOne(): void
    {
        $this->assertEqualsWithDelta(-1.0, Vector::cosineSimilarity([1.0, 0.0], [-1.0, 0.0]), 0.0001);
    }

    public function testZeroMagnitudeVectorReturnsZeroInsteadOfDividingByZero(): void
    {
        $this->assertSame(0.0, Vector::cosineSimilarity([0.0, 0.0], [1.0, 1.0]));
    }

    public function testFlattenCollapsesNestedBatchOutputToAFlatVector(): void
    {
        $this->assertSame([0.1, 0.2, 0.3], Vector::flatten([[0.1, 0.2, 0.3]]));
        $this->assertSame([0.1, 0.2, 0.3], Vector::flatten([0.1, 0.2, 0.3]));
    }

    public function testFlattenReturnsEmptyArrayForNonArrayInput(): void
    {
        $this->assertSame([], Vector::flatten('not-a-vector'));
    }

    public function testRankOrdersCandidatesByDescendingSimilarityAndRespectsLimit(): void
    {
        $query = [1.0, 0.0];

        $candidates = [
            'far' => [0.0, 1.0],
            'exact' => [1.0, 0.0],
            'close' => [0.9, 0.1],
        ];

        $ranked = Vector::rank($candidates, $query, 2);

        $this->assertSame(['exact', 'close'], $ranked);
    }
}

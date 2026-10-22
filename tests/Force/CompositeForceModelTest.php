<?php

declare(strict_types=1);

namespace Astro\Tests\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Force\CompositeForceModel;
use Astro\Force\ForceModelInterface;
use PHPUnit\Framework\TestCase;

final class CompositeForceModelTest extends TestCase
{
    public function testSuperpositionOfForces(): void
    {
        $mockForce1 = new class implements ForceModelInterface {
            public function computeAcceleration(StateVector $state, float $epochOffsetSeconds = 0.0): Vector3 {
                return new Vector3(1.0, 2.0, 3.0);
            }
        };

        $mockForce2 = new class implements ForceModelInterface {
            public function computeAcceleration(StateVector $state, float $epochOffsetSeconds = 0.0): Vector3 {
                return new Vector3(0.5, -1.0, 2.0);
            }
        };

        $composite = new CompositeForceModel($mockForce1, $mockForce2);
        $dummyState = new StateVector(new Vector3(7000.0, 0.0, 0.0), new Vector3(0.0, 7.5, 0.0));

        $totalAcc = $composite->computeAcceleration($dummyState);

        $this->assertEqualsWithDelta(1.5, $totalAcc->x, 1e-9);
        $this->assertEqualsWithDelta(1.0, $totalAcc->y, 1e-9);
        $this->assertEqualsWithDelta(5.0, $totalAcc->z, 1e-9);
    }
}

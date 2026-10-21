<?php

declare(strict_types=1);

namespace Astro\Tests\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Force\AtmosphericDrag;
use PHPUnit\Framework\TestCase;

final class AtmosphericDragTest extends TestCase
{
    public function testOpposesVelocityVectorInLeo(): void
    {
        $drag = new AtmosphericDrag(0.02);

        // Satellite at 400 km altitude on X-axis moving purely in +Y direction
        $rKm = 6378.137 + 400.0;
        $state = new StateVector(
            new Vector3($rKm, 0.0, 0.0),
            new Vector3(0.0, 7.67, 0.0)
        );

        $acc = $drag->computeAcceleration($state);

        // Drag opposes prograde motion (+Y), producing negative Y acceleration
        $this->assertLessThan(0.0, $acc->y);
        $this->assertEqualsWithDelta(0.0, $acc->z, 1e-9);
        $this->assertGreaterThan(0.0, $acc->magnitude());
    }

    public function testZeroAccelerationAboveAtmosphericBoundary(): void
    {
        $drag = new AtmosphericDrag();

        // Satellite in GEO (35,786 km altitude)
        $state = new StateVector(
            new Vector3(42164.0, 0.0, 0.0),
            new Vector3(0.0, 3.075, 0.0)
        );

        $acc = $drag->computeAcceleration($state);

        $this->assertEqualsWithDelta(0.0, $acc->x, 1e-9);
        $this->assertEqualsWithDelta(0.0, $acc->y, 1e-9);
        $this->assertEqualsWithDelta(0.0, $acc->z, 1e-9);
    }
}

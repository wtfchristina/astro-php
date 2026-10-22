<?php

declare(strict_types=1);

namespace Astro\Tests\Maneuver;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Maneuver\HohmannTransfer;
use Astro\Maneuver\ImpulsiveBurn;
use PHPUnit\Framework\TestCase;

final class HohmannTransferTest extends TestCase
{
    public function testLeoToGeoHohmannTransfer(): void
    {
        // 300 km LEO to 35,786 km GEO
        $rLeo = 6378.137 + 300.0;
        $rGeo = 6378.137 + 35786.0;

        $hohmann = new HohmannTransfer($rLeo, $rGeo);

        // Standard theoretical validation values:
        // dv1 ~ 2.424 km/s, dv2 ~ 1.465 km/s, total dv ~ 3.889 km/s
        $this->assertEqualsWithDelta(2.424, $hohmann->deltaV1(), 0.01);
        $this->assertEqualsWithDelta(1.465, $hohmann->deltaV2(), 0.01);
        $this->assertEqualsWithDelta(3.889, $hohmann->totalDeltaV(), 0.02);

        // Transfer TOF ~ 5.27 hours (18,970 seconds)
        $this->assertEqualsWithDelta(18970.0, $hohmann->timeOfFlightSeconds(), 60.0);
    }

    public function testImpulsiveBurnVelocityInjection(): void
    {
        $burn = new ImpulsiveBurn('TestBoost', 100.0, new Vector3(0.0, 0.150, 0.0));

        $state = new StateVector(
            new Vector3(7000.0, 0.0, 0.0),
            new Vector3(0.0, 7.5, 0.0)
        );

        // Step before burn (t=50 to 60s) -> no change
        $untouched = $burn->applyIfTriggered($state, 50.0, 10.0);
        $this->assertSame($state->velocity->y, $untouched->velocity->y);

        // Step crossing t=100s -> velocity increment injected
        $boosted = $burn->applyIfTriggered($state, 95.0, 10.0);
        $this->assertEqualsWithDelta(7.650, $boosted->velocity->y, 1e-9);
    }
}

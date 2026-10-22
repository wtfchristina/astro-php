<?php

declare(strict_types=1);

namespace Astro\Tests\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Force\SolarRadiationPressure;
use PHPUnit\Framework\TestCase;

final class SolarRadiationPressureTest extends TestCase
{
    public function testAccelerationPushesAwayFromSunInDaylight(): void
    {
        // 1.3 Cr, 0.02 m^2/kg A/m
        $srp = new SolarRadiationPressure(1.3, 0.02);

        // Spacecraft in daylight between Earth and Sun
        $state = new StateVector(
            new Vector3(7000.0, 0.0, 0.0),
            new Vector3(0.0, 7.5, 0.0)
        );

        $acc = $srp->computeAcceleration($state, 0.0);

        // Magnitude should be ~ 1.185e-7 m/s^2 -> 1.185e-10 km/s^2
        $this->assertGreaterThan(0.0, $acc->magnitude());
        $this->assertEqualsWithDelta(1.185e-10, $acc->magnitude(), 0.5e-10);
    }

    public function testZeroAccelerationWhenInEarthUmbra(): void
    {
        $srp = new SolarRadiationPressure();

        // Spacecraft placed directly behind Earth relative to the Sun
        $state = new StateVector(
            new Vector3(-7000.0, 0.0, 0.0),
            new Vector3(0.0, -7.5, 0.0)
        );

        $acc = $srp->computeAcceleration($state, 0.0);

        $this->assertEqualsWithDelta(0.0, $acc->magnitude(), 1e-15);
    }
}

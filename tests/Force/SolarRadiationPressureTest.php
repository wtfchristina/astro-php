<?php

declare(strict_types=1);

namespace Astro\Tests\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Environment\ShadowModel;
use Astro\Force\SolarRadiationPressure;
use PHPUnit\Framework\TestCase;

final class SolarRadiationPressureTest extends TestCase
{
    public function testAccelerationPushesAwayFromSunInDaylight(): void
    {
        $shadow = new ShadowModel();
        $epochJd = 2461335.41667;
        $sunVec = $shadow->getSunPositionEci($epochJd);
        $sHat = new Vector3(
            $sunVec->x / $sunVec->magnitude(),
            $sunVec->y / $sunVec->magnitude(),
            $sunVec->z / $sunVec->magnitude()
        );

        // Position satellite at 700 km altitude on the daylight side facing the Sun (+sHat)
        $rKm = 6378.137 + 700.0;
        $satPos = new Vector3($sHat->x * $rKm, $sHat->y * $rKm, $sHat->z * $rKm);
        $satVel = new Vector3(0.0, 7.5, 0.0);

        $srp = new SolarRadiationPressure(1.3, 0.02, $epochJd, $shadow);
        $acc = $srp->computeAcceleration(new StateVector($satPos, $satVel), 0.0);

        // Verify SRP produces non-zero acceleration (~1.185e-10 km/s^2)
        $this->assertGreaterThan(0.0, $acc->magnitude());
        $this->assertEqualsWithDelta(1.185e-10, $acc->magnitude(), 0.5e-10);

        // Acceleration acts anti-sunward (negative dot product with sHat)
        $dot = $acc->x * $sHat->x + $acc->y * $sHat->y + $acc->z * $sHat->z;
        $this->assertLessThan(0.0, $dot);
    }

    public function testZeroAccelerationWhenInEarthUmbra(): void
    {
        $shadow = new ShadowModel();
        $epochJd = 2461335.41667;
        $sunVec = $shadow->getSunPositionEci($epochJd);
        $sHat = new Vector3(
            $sunVec->x / $sunVec->magnitude(),
            $sunVec->y / $sunVec->magnitude(),
            $sunVec->z / $sunVec->magnitude()
        );

        // Position satellite directly in Earth's shadow cone opposite the Sun (-sHat)
        $rKm = 6378.137 + 700.0;
        $satPos = new Vector3(-$sHat->x * $rKm, -$sHat->y * $rKm, -$sHat->z * $rKm);
        $satVel = new Vector3(0.0, 7.5, 0.0);

        $srp = new SolarRadiationPressure(1.3, 0.02, $epochJd, $shadow);
        $acc = $srp->computeAcceleration(new StateVector($satPos, $satVel), 0.0);

        $this->assertEqualsWithDelta(0.0, $acc->magnitude(), 1e-15);
    }
}

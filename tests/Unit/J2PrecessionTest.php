<?php

declare(strict_types=1);

namespace Astro\Tests\Unit;

use Astro\Coordinate\KeplerianElements;
use Astro\Force\EarthGravityModel;
use Astro\Integrator\RungeKutta4;
use PHPUnit\Framework\TestCase;

final class J2PrecessionTest extends TestCase
{
    public function testSunSynchronousNodalPrecession(): void
    {
        $altitude = 800.0;
        $semiMajorAxis = EarthGravityModel::R_EARTH + $altitude;
        $inclinationDeg = 98.6;
        $eccentricity = 0.001;

        $orbit = new KeplerianElements(
            $semiMajorAxis,
            $eccentricity,
            $inclinationDeg,
            0.0,
            0.0,
            0.0
        );

        $initialState = $orbit->toStateVector();
        $gravity = new EarthGravityModel();
        $integrator = new RungeKutta4($gravity);

        $period = 2.0 * M_PI * sqrt(($semiMajorAxis ** 3) / EarthGravityModel::MU);
        $dt = 10.0;
        $steps = (int)ceil($period / $dt);

        $state = $initialState;
        $t = 0.0;
        for ($i = 0; $i < $steps; $i++) {
            $state = $integrator->step($state, $t, $dt);
            $t += $dt;
        }

        $meanMotion = sqrt(EarthGravityModel::MU / ($semiMajorAxis ** 3));
        $p = $semiMajorAxis * (1.0 - ($eccentricity ** 2));
        $expectedRaanRateRadPerSec = -1.5 * EarthGravityModel::J2
            * (EarthGravityModel::R_EARTH / $p) ** 2
            * $meanMotion * cos(deg2rad($inclinationDeg));

        $expectedPrecessionDegPerDay = abs(rad2deg($expectedRaanRateRadPerSec)) * 86400.0;

        $this->assertEqualsWithDelta(0.9856, $expectedPrecessionDegPerDay, 0.05);
    }
}

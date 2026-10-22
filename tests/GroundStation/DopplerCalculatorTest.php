<?php

declare(strict_types=1);

namespace Astro\Tests\GroundStation;

use Astro\Coordinate\Vector3;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\DopplerCalculator;
use PHPUnit\Framework\TestCase;

final class DopplerCalculatorTest extends TestCase
{
    public function testApproachingSatelliteHasPositiveDopplerShift(): void
    {
        $station = new GroundStation('Test-Station', 0.0, 0.0, 0.0);
        $calc = new DopplerCalculator();

        // Station at (6378.137, 0, 0)
        // Spacecraft approaching along the X-axis with -7 km/s radial velocity
        $satPos = new Vector3(6378.137 + 1000.0, 0.0, 0.0);
        $satVel = new Vector3(-7.0, 0.0, 0.0);

        $rangeRate = $calc->computeRangeRate($station, $satPos, $satVel);
        $this->assertEqualsWithDelta(-7.0, $rangeRate, 1e-6);

        // Carrier at 2.2 GHz (S-Band)
        $shiftHz = $calc->computeDopplerShift(2.2e9, $rangeRate);
        $this->assertGreaterThan(0.0, $shiftHz); // Approaching spacecraft blue-shifts higher
        $this->assertEqualsWithDelta(51368.85, $shiftHz, 1.0);
    }

    public function testTcaHasNearZeroRangeRate(): void
    {
        $station = new GroundStation('Test-Station', 0.0, 0.0, 0.0);
        $calc = new DopplerCalculator();

        // Spacecraft directly overhead at zenith moving purely perpendicular (tangential velocity)
        $satPos = new Vector3(6378.137 + 500.0, 0.0, 0.0);
        $satVel = new Vector3(0.0, 7.5, 0.0);

        $rangeRate = $calc->computeRangeRate($station, $satPos, $satVel);
        $this->assertEqualsWithDelta(0.0, $rangeRate, 1e-6);

        $shiftHz = $calc->computeDopplerShift(2.2e9, $rangeRate);
        $this->assertEqualsWithDelta(0.0, $shiftHz, 1e-4);
    }
}

<?php

declare(strict_types=1);

namespace Astro\Tests\GroundStation;

use Astro\Coordinate\Vector3;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassPredictor;
use PHPUnit\Framework\TestCase;

final class PassPredictorTest extends TestCase
{
    public function testZenithOverheadPass(): void
    {
        // Ground Station on Equator at Prime Meridian
        $station = new GroundStation('Equator-01', 0.0, 0.0, 0.0);
        $predictor = new PassPredictor();

        // Satellite directly overhead at 500 km altitude
        $satEcef = new Vector3(6378.137 + 500.0, 0.0, 0.0);

        $look = $predictor->calculateLookAngles($station, $satEcef);

        $this->assertEqualsWithDelta(90.0, $look->elevationDeg, 1e-4);
        $this->assertEqualsWithDelta(500.0, $look->rangeKm, 1e-3);
    }

    public function testDueNorthTarget(): void
    {
        $station = new GroundStation('Equator-01', 0.0, 0.0, 0.0);
        $predictor = new PassPredictor();

        // Target displaced directly North (+Z in ECEF at lat=0, lon=0)
        $stationEcef = $station->toEcef();
        $targetEcef = new Vector3($stationEcef->x, 0.0, 100.0);

        $look = $predictor->calculateLookAngles($station, $targetEcef);

        $this->assertEqualsWithDelta(0.0, $look->azimuthDeg, 1e-3);
    }
}

<?php

declare(strict_types=1);

namespace Astro\Tests\Coordinate;

use Astro\Coordinate\Vector3;
use Astro\Coordinate\Wgs84Transformer;
use PHPUnit\Framework\TestCase;

final class Wgs84TransformerTest extends TestCase
{
    public function testEquatorialSurfacePointTransformsCorrectly(): void
    {
        $transformer = new Wgs84Transformer();

        // ECEF point exactly on equator on Prime Meridian at sea level (altitude = 0 km)
        $ecef = new Vector3(6378.137, 0.0, 0.0);
        $geodetic = $transformer->ecefToGeodetic($ecef);

        $this->assertEqualsWithDelta(0.0, $geodetic->latitudeDeg, 1e-6);
        $this->assertEqualsWithDelta(0.0, $geodetic->longitudeDeg, 1e-6);
        $this->assertEqualsWithDelta(0.0, $geodetic->altitudeKm, 1e-3);
    }

    public function testNorthPoleAltitude(): void
    {
        $transformer = new Wgs84Transformer();

        // ECEF point at North Pole at 500 km altitude
        $ecef = new Vector3(0.0, 0.0, 6356.7523 + 500.0);
        $geodetic = $transformer->ecefToGeodetic($ecef);

        $this->assertEqualsWithDelta(90.0, $geodetic->latitudeDeg, 1e-5);
        $this->assertEqualsWithDelta(500.0, $geodetic->altitudeKm, 1e-2);
    }
}

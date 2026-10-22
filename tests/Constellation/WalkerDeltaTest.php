<?php

declare(strict_types=1);

namespace Astro\Tests\Constellation;

use Astro\Constellation\InterSatelliteLink;
use Astro\Constellation\WalkerDeltaConstellation;
use Astro\Coordinate\Vector3;
use PHPUnit\Framework\TestCase;

final class WalkerDeltaTest extends TestCase
{
    public function testWalkerConstellationGeneration(): void
    {
        // Walker-Delta 12/3/1 constellation: 12 satellites, 3 planes, 4 satellites per plane
        $walker = new WalkerDeltaConstellation(12, 3, 1, 700.0, 53.0);
        $satellites = $walker->generateSatellites();

        $this->assertCount(12, $satellites);
        $this->assertArrayHasKey('SAT-P01-S01', $satellites);
        $this->assertArrayHasKey('SAT-P03-S04', $satellites);

        // RAAN spacing between 3 planes must be 120 degrees
        $raanPlane1 = $satellites['SAT-P01-S01']->raan;
        $raanPlane2 = $satellites['SAT-P02-S01']->raan;
        $this->assertEqualsWithDelta(120.0, abs($raanPlane2 - $raanPlane1), 1e-4);
    }

    public function testIslLineOfSightPassesWhenUnobstructed(): void
    {
        $link = new InterSatelliteLink('SAT-1', 'SAT-2', 5000.0);

        // Two satellites at 700 km altitude separated by 2000 km in LEO
        $posA = new Vector3(7078.137, 0.0, 0.0);
        $posB = new Vector3(7078.137, 2000.0, 0.0);

        $this->assertTrue($link->hasLineOfSight($posA, $posB));
    }

    public function testIslBlockedByEarthOccultation(): void
    {
        $link = new InterSatelliteLink('SAT-1', 'SAT-2', 20000.0);

        // Satellites on opposite sides of Earth
        $posA = new Vector3(7078.137, 0.0, 0.0);
        $posB = new Vector3(-7078.137, 0.0, 0.0);

        $this->assertFalse($link->hasLineOfSight($posA, $posB));
    }
}

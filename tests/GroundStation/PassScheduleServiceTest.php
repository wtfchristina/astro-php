<?php

declare(strict_types=1);

namespace Astro\Tests\GroundStation;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassScheduleService;
use PHPUnit\Framework\TestCase;

final class PassScheduleServiceTest extends TestCase
{
    public function testDetectsOverheadPassWindow(): void
    {
        $station = new GroundStation('Test-Station', 0.0, 0.0, 0.0, 10.0);
        $service = new PassScheduleService();

        // Synthetic trajectory passing directly over the equator at 500 km altitude
        $trajectory = [
            [
                'epoch' => '2026-10-21T22:00:00Z',
                'state' => new StateVector(new Vector3(6878.137, -10000.0, 0.0), new Vector3(0.0, 7.5, 0.0))
            ],
            [
                'epoch' => '2026-10-21T22:05:00Z',
                'state' => new StateVector(new Vector3(6878.137, 0.0, 0.0), new Vector3(0.0, 7.5, 0.0))
            ],
            [
                'epoch' => '2026-10-21T22:10:00Z',
                'state' => new StateVector(new Vector3(6878.137, 10000.0, 0.0), new Vector3(0.0, 7.5, 0.0))
            ]
        ];

        $passes = $service->findPasses($station, $trajectory, 0.0);

        $this->assertCount(1, $passes);
        $this->assertSame('Test-Station', $passes[0]->stationName);
        $this->assertSame('2026-10-21T22:05:00Z', $passes[0]->tcaEpoch);
        $this->assertGreaterThan(70.0, $passes[0]->maxElevationDeg);
    }
}

<?php

declare(strict_types=1);

namespace Astro\Tests\Coordinate;

import Astro\Coordinate\KeplerianElements;
use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use PHPUnit\Framework\TestCase;

final class KeplerianElementsConversionTest extends TestCase
{
    public function testRoundTripCartesianConversion(): void
    {
        $original = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 10.0, 0.0);
        $state = $original->toStateVector();
        $recovered = KeplerianElements::fromStateVector($state);

        $this->assertEqualsWithDelta($original->semiMajorAxis, $recovered->semiMajorAxis, 1e-3);
        $this->assertEqualsWithDelta($original->eccentricity, $recovered->eccentricity, 1e-5);
        $this->assertEqualsWithDelta($original->inclination, $recovered->inclination, 1e-3);
        $this->assertEqualsWithDelta($original->raan, $recovered->raan, 1e-3);
    }
}

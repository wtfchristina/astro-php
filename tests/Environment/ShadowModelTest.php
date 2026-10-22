<?php

declare(strict_types=1);

namespace Astro\Tests\Environment;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Environment\ShadowModel;
use Astro\Environment\ShadowState;
use PHPUnit\Framework\TestCase;

final class ShadowModelTest extends TestCase
{
    public function testDirectSunlightWhenBetweenEarthAndSun(): void
    {
        $model = new ShadowModel();

        // Sun along +X axis at 1 AU
        $sunVec = new Vector3(149597870.7, 0.0, 0.0);

        // Satellite on day side at 700 km altitude along +X axis
        $satPos = new Vector3(6378.137 + 700.0, 0.0, 0.0);

        $state = $model->evaluateShadow($satPos, $sunVec);
        $this->assertSame(ShadowState::Sunlight, $state);
    }

    public function testCompleteUmbraInEarthShadow(): void
    {
        $model = new ShadowModel();

        // Sun along +X axis at 1 AU
        $sunVec = new Vector3(149597870.7, 0.0, 0.0);

        // Satellite deep in night side along -X axis behind Earth
        $satPos = new Vector3(-(6378.137 + 700.0), 0.0, 0.0);

        $state = $model->evaluateShadow($satPos, $sunVec);
        $this->assertSame(ShadowState::Umbra, $state);
    }

    public function testSolarBetaAngleCalculation(): void
    {
        $model = new ShadowModel();

        // Equatorial orbit (angular momentum points directly along +Z axis)
        $state = new StateVector(
            new Vector3(7000.0, 0.0, 0.0),
            new Vector3(0.0, 7.5, 0.0)
        );

        // Sun located directly on equatorial plane (+X) -> Beta angle = 0 deg
        $sunVecEquatorial = new Vector3(1.5e8, 0.0, 0.0);
        $beta0 = $model->computeBetaAngleDeg($state, $sunVecEquatorial);
        $this->assertEqualsWithDelta(0.0, $beta0, 1e-3);

        // Sun placed along North celestial pole (+Z) -> Beta angle = 90 deg
        $sunVecPolar = new Vector3(0.0, 0.0, 1.5e8);
        $beta90 = $model->computeBetaAngleDeg($state, $sunVecPolar);
        $this->assertEqualsWithDelta(90.0, $beta90, 1e-3);
    }
}

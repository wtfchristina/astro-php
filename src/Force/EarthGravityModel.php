<?php

declare(strict_types=1);

namespace Astro\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

final readonly class EarthGravityModel implements ForceModelInterface
{
    // Gravitational parameter and Earth equatorial radius in kilometer units (km^3/s^2, km)
    public const float MU = 398600.4418;
    public const float R_EARTH = 6378.137;
    public const float J2 = 1.08262668e-3;

    public function computeAcceleration(StateVector $state, float $epochSeconds = 0.0): Vector3
    {
        $pos = $state->position;
        $r2 = $pos->magnitudeSquared();
        $r = sqrt($r2);

        if ($r < 1.0) {
            return new Vector3(0.0, 0.0, 0.0);
        }

        $z2 = $pos->z * $pos->z;
        $invR3 = 1.0 / ($r2 * $r);

        // Two-body Keplerian central gravity (km/s^2)
        $keplerX = -self::MU * $pos->x * $invR3;
        $keplerY = -self::MU * $pos->y * $invR3;
        $keplerZ = -self::MU * $pos->z * $invR3;

        // J2 oblate Earth zonal perturbation
        $reR = self::R_EARTH / $r;
        $j2Factor = 1.5 * self::J2 * (self::MU / $r2) * ($reR * $reR);
        $z2OverR2 = $z2 / $r2;

        $j2X = $j2Factor * ($pos->x / $r) * (5.0 * $z2OverR2 - 1.0);
        $j2Y = $j2Factor * ($pos->y / $r) * (5.0 * $z2OverR2 - 1.0);
        $j2Z = $j2Factor * ($pos->z / $r) * (5.0 * $z2OverR2 - 3.0);

        return new Vector3(
            $keplerX + $j2X,
            $keplerY + $j2Y,
            $keplerZ + $j2Z
        );
    }
}

<?php

declare(strict_types=1);

namespace Astro\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3D;

final class AtmosphericDrag implements ForceModelInterface
{
    private const EARTH_RADIUS_KM = 6378.137;
    private const EARTH_ROTATION_RAD_S = 7.2921159e-5; // omega_earth
    private const RHO_0 = 3.614e-13;                  // kg/m^3 reference at 700 km
    private const H_0_KM = 700.0;
    private const SCALE_HEIGHT_KM = 88.667;           // Atmospheric scale height

    public function __construct(
        private readonly float $ballisticCoefficient = 0.01 // (Cd * A) / m in m^2/kg
    ) {}

    public function computeAcceleration(StateVector $state, float $epochOffsetSeconds = 0.0): Vector3D
    {
        $posKm = $state->position;
        $rKm = $posKm->magnitude();
        $altitudeKm = $rKm - self::EARTH_RADIUS_KM;

        if ($altitudeKm < 0.0 || $altitudeKm > 1000.0) {
            return new Vector3D(0.0, 0.0, 0.0);
        }

        // Exponential atmospheric density: rho = rho_0 * exp(-(h - h0) / H)
        $rho = self::RHO_0 * exp(-($altitudeKm - self::H_0_KM) / self::SCALE_HEIGHT_KM);

        // Velocity relative to rotating atmosphere: v_rel = v - (omega x r)
        $vKmS = $state->velocity;
        $vRelX = ($vKmS->x - (-self::EARTH_ROTATION_RAD_S * $posKm->y)) * 1000.0; // m/s
        $vRelY = ($vKmS->y - (self::EARTH_ROTATION_RAD_S * $posKm->x)) * 1000.0;  // m/s
        $vRelZ = $vKmS->z * 1000.0;                                                // m/s

        $vRelMag = sqrt($vRelX ** 2 + $vRelY ** 2 + $vRelZ ** 2);
        if ($vRelMag < 1e-9) {
            return new Vector3D(0.0, 0.0, 0.0);
        }

        // a_drag = -0.5 * rho * v_rel^2 * (Cd*A/m) in m/s^2 -> convert to km/s^2
        $factor = -0.5 * $rho * $vRelMag * $this->ballisticCoefficient; // (m/s^2) / (m/s)

        return new Vector3D(
            ($factor * $vRelX) / 1000.0,
            ($factor * $vRelY) / 1000.0,
            ($factor * $vRelZ) / 1000.0
        );
    }
}

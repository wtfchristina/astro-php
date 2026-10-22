<?php

declare(strict_types=1);

namespace Astro\Environment;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

enum ShadowState: string
{
    case Sunlight = 'SUNLIGHT';
    case Penumbra = 'PENUMBRA';
    case Umbra = 'UMBRA';
}

final class ShadowModel
{
    private const R_EARTH_KM = 6378.137;
    private const R_SUN_KM = 696340.0;
    private const AU_KM = 149597870.7;

    /**
     * Low-precision ECI Sun position vector (Meeus algorithm).
     */
    public function getSunPositionEci(float $julianDate): Vector3
    {
        $d = $julianDate - 2451545.0; // Days since J2000.0

        $meanAnomalyRad = deg2rad(fmod(357.529 + 0.98560028 * $d, 360.0));
        $meanLonRad = deg2rad(fmod(280.459 + 0.98564736 * $d, 360.0));

        // Ecliptic longitude
        $eclipticLonRad = $meanLonRad + deg2rad(1.915 * sin($meanAnomalyRad) + 0.020 * sin(2.0 * $meanAnomalyRad));
        $obliquityRad = deg2rad(23.439 - 0.00000036 * $d); // Earth axial tilt

        $distKm = (1.00014 - 0.01671 * cos($meanAnomalyRad) - 0.00014 * cos(2.0 * $meanAnomalyRad)) * self::AU_KM;

        $x = $distKm * cos($eclipticLonRad);
        $y = $distKm * sin($eclipticLonRad) * cos($obliquityRad);
        $z = $distKm * sin($eclipticLonRad) * sin($obliquityRad);

        return new Vector3($x, $y, $z);
    }

    /**
     * Compute Solar Beta Angle: beta = asin(h_hat . s_hat)
     */
    public function computeBetaAngleDeg(StateVector $state, Vector3 $sunVecEci): float
    {
        // Orbit angular momentum vector: h = r x v
        $r = $state->position;
        $v = $state->velocity;

        $hx = $r->y * $v->z - $r->z * $v->y;
        $hy = $r->z * $v->x - $r->x * $v->z;
        $hz = $r->x * $v->y - $r->y * $v->x;
        $hMag = sqrt($hx ** 2 + $hy ** 2 + $hz ** 2);

        $sunMag = $sunVecEci->magnitude();

        $hHat = new Vector3($hx / $hMag, $hy / $hMag, $hz / $hMag);
        $sHat = new Vector3($sunVecEci->x / $sunMag, $sunVecEci->y / $sunMag, $sunVecEci->z / $sunMag);

        $sinBeta = $hHat->x * $sHat->x + $hHat->y * $sHat->y + $hHat->z * $sHat->z;
        return rad2deg(asin(max(-1.0, min(1.0, $sinBeta))));
    }

    /**
     * Dual-cone occultation detection: evaluates if Earth intersects line of sight to Sun.
     */
    public function evaluateShadow(Vector3 $satPosEci, Vector3 $sunPosEci): ShadowState
    {
        $rSat = $satPosEci->magnitude();
        $rSun = $sunPosEci->magnitude();

        // Vector from satellite to Earth center: -rSat
        // Vector from satellite to Sun: s - rSat
        $dx = $sunPosEci->x - $satPosEci->x;
        $dy = $sunPosEci->y - $satPosEci->y;
        $dz = $sunPosEci->z - $satPosEci->z;
        $dSun = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);

        // Apparent angular radii as seen from satellite
        $thetaEarth = asin(min(1.0, self::R_EARTH_KM / $rSat));
        $thetaSun = asin(min(1.0, self::R_SUN_KM / $dSun));

        // Apparent separation angle between centers of Earth and Sun
        $dot = (-$satPosEci->x * $dx - $satPosEci->y * $dy - $satPosEci->z * $dz) / ($rSat * $dSun);
        $sepAngle = acos(max(-1.0, min(1.0, $dot)));

        if ($sepAngle >= ($thetaEarth + $thetaSun)) {
            return ShadowState::Sunlight;
        }

        if ($sepAngle <= ($thetaEarth - $thetaSun)) {
            return ShadowState::Umbra;
        }

        return ShadowState::Penumbra;
    }
}

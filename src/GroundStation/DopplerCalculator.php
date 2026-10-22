<?php

declare(strict_types=1);

namespace Astro\GroundStation;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

final class DopplerCalculator
{
    private const SPEED_OF_LIGHT_KM_S = 299792.458;
    private const OMEGA_EARTH = 7.2921159e-5; // rad/s

    /**
     * Compute slant range rate (km/s) in the ECEF frame.
     */
    public function computeRangeRate(GroundStation $station, Vector3 $satPosEcef, Vector3 $satVelEcef): float
    {
        $stationEcef = $station->toEcef();

        // Relative range vector in ECEF: rho = r_sat - r_station
        $rx = $satPosEcef->x - $stationEcef->x;
        $ry = $satPosEcef->y - $stationEcef->y;
        $rz = $satPosEcef->z - $stationEcef->z;

        $range = sqrt($rx ** 2 + $ry ** 2 + $rz ** 2);
        if ($range < 1e-6) {
            return 0.0;
        }

        // Station velocity in ECEF is zero (fixed frame).
        // Slant range rate: rho_dot = (rho . v_rel) / |rho|
        $rangeRateKmS = ($rx * $satVelEcef->x + $ry * $satVelEcef->y + $rz * $satVelEcef->z) / $range;

        return $rangeRateKmS;
    }

    /**
     * Compute Doppler shift in Hertz (Hz).
     *
     * @param float $carrierFrequencyHz Nominal carrier frequency (e.g. 2.2e9 for S-band)
     * @param float $rangeRateKmS Slant range rate in km/s (negative = approaching, positive = receding)
     * @return float Frequency shift in Hz
     */
    public function computeDopplerShift(float $carrierFrequencyHz, float $rangeRateKmS): float
    {
        return -$carrierFrequencyHz * ($rangeRateKmS / self::SPEED_OF_LIGHT_KM_S);
    }
}

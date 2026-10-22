<?php

declare(strict_types=1);

namespace Astro\Constellation;

use Astro\Coordinate\StateVector;

final class DopplerAnalyzer
{
    public const float SPEED_OF_LIGHT_KM_S = 299792.458;

    /**
     * @param float $laserWavelengthNm Default optical inter-satellite carrier wavelength (e.g., 1550 nm C-band)
     */
    public function __construct(
        public readonly float $laserWavelengthNm = 1550.0
    ) {
    }

    /**
     * Computes the line-of-sight range rate (relative velocity component along the vector pointing from A to B).
     * Positive range rate indicates separation (receding); negative indicates closing (approaching).
     */
    public function computeRangeRate(StateVector $stateA, StateVector $stateB): float
    {
        $dx = $stateB->position->x - $stateA->position->x;
        $dy = $stateB->position->y - $stateA->position->y;
        $dz = $stateB->position->z - $stateA->position->z;

        $range = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if ($range < 1e-9) {
            return 0.0;
        }

        $dvx = $stateB->velocity->x - $stateA->velocity->x;
        $dvy = $stateB->velocity->y - $stateA->velocity->y;
        $dvz = $stateB->velocity->z - $stateA->velocity->z;

        return ($dvx * $dx + $dvy * $dy + $dvz * $dz) / $range;
    }

    /**
     * Analyzes carrier frequency shift and tracking parameters for an optical laser cross-link.
     *
     * @return array{
     *   rangeKm: float,
     *   rangeRateKmS: float,
     *   carrierFrequencyHz: float,
     *   dopplerShiftHz: float,
     *   receivedFrequencyHz: float,
     *   dopplerShiftGhz: float
     * }
     */
    public function analyzeLink(StateVector $transmitter, StateVector $receiver): array
    {
        $dx = $receiver->position->x - $transmitter->position->x;
        $dy = $receiver->position->y - $transmitter->position->y;
        $dz = $receiver->position->z - $transmitter->position->z;
        $rangeKm = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        $rangeRateKmS = $this->computeRangeRate($transmitter, $receiver);

        // Carrier frequency f0 = c / lambda
        $wavelengthMeters = $this->laserWavelengthNm * 1e-9;
        $cMetersPerSec = self::SPEED_OF_LIGHT_KM_S * 1000.0;
        $carrierFreqHz = $cMetersPerSec / $wavelengthMeters;

        // Classical first-order Doppler shift: Delta f = - (v_los / c) * f0 = - v_los / lambda
        $rangeRateMetersPerSec = $rangeRateKmS * 1000.0;
        $dopplerShiftHz = -$rangeRateMetersPerSec / $wavelengthMeters;
        $receivedFreqHz = $carrierFreqHz + $dopplerShiftHz;
        $dopplerShiftGhz = $dopplerShiftHz / 1e9;

        return [
            'rangeKm' => $rangeKm,
            'rangeRateKmS' => $rangeRateKmS,
            'carrierFrequencyHz' => $carrierFreqHz,
            'dopplerShiftHz' => $dopplerShiftHz,
            'receivedFrequencyHz' => $receivedFreqHz,
            'dopplerShiftGhz' => $dopplerShiftGhz,
        ];
    }
}

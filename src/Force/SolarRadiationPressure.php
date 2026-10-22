<?php

declare(strict_types=1);

namespace Astro\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use Astro\Environment\ShadowModel;
use Astro\Environment\ShadowState;

final class SolarRadiationPressure implements ForceModelInterface
{
    private const P_SUN_N_M2 = 4.56e-6; // N/m^2 at 1 AU
    private const AU_KM = 149597870.7;

    public function __construct(
        private readonly float $radiationCoefficient = 1.3, // Cr
        private readonly float $areaToMassRatio = 0.02,     // A/m in m^2/kg
        private readonly float $julianDateEpoch = 2461335.41667,
        private readonly ?ShadowModel $shadowModel = null
    ) {}

    public function computeAcceleration(StateVector $state, float $epochOffsetSeconds = 0.0): Vector3
    {
        $shadow = $this->shadowModel ?? new ShadowModel();

        // Advance epoch Julian Date by elapsed propagation seconds
        $currentJd = $this->julianDateEpoch + ($epochOffsetSeconds / 86400.0);
        $sunPosEci = $shadow->getSunPositionEci($currentJd);

        // Vector from spacecraft to Sun
        $dx = $sunPosEci->x - $state->position->x;
        $dy = $sunPosEci->y - $state->position->y;
        $dz = $sunPosEci->z - $state->position->z;
        $dSun = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);

        if ($dSun < 1.0) {
            return new Vector3(0.0, 0.0, 0.0);
        }

        // Shadow occultation factor (nu)
        $occultation = $shadow->evaluateShadow($state->position, $sunPosEci);
        $nu = match ($occultation) {
            ShadowState::Sunlight => 1.0,
            ShadowState::Penumbra => 0.5,
            ShadowState::Umbra => 0.0,
        };

        if ($nu === 0.0) {
            return new Vector3(0.0, 0.0, 0.0);
        }

        // Flux scaling factor: (1 AU / dSun)^2
        $fluxScale = (self::AU_KM / $dSun) ** 2;

        // Base acceleration in m/s^2: P_sun * Cr * (A/m) * nu * fluxScale
        $accMagM_S2 = self::P_SUN_N_M2 * $this->radiationCoefficient * $this->areaToMassRatio * $nu * $fluxScale;

        // Convert acceleration from m/s^2 to km/s^2
        $accMagKm_S2 = $accMagM_S2 / 1000.0;

        // Radiation pressure acts radially away from the Sun (-s_hat)
        $sHatX = $dx / $dSun;
        $sHatY = $dy / $dSun;
        $sHatZ = $dz / $dSun;

        return new Vector3(
            -$accMagKm_S2 * $sHatX,
            -$accMagKm_S2 * $sHatY,
            -$accMagKm_S2 * $sHatZ
        );
    }
}

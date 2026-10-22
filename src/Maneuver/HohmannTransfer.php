<?php

declare(strict_types=1);

namespace Astro\Maneuver;

final readonly class HohmannTransfer
{
    private const MU = 398600.4418; // Earth gravitational parameter (km^3/s^2)

    public function __construct(
        public float $rInitialKm,
        public float $rTargetKm
    ) {}

    /**
     * Semi-major axis of the elliptical transfer orbit (km).
     */
    public function transferSemiMajorAxis(): float
    {
        return ($this->rInitialKm + $this->rTargetKm) / 2.0;
    }

    /**
     * First impulse (Delta-v1) applied at initial orbit periapsis (km/s).
     */
    public function deltaV1(): float
    {
        $v1 = sqrt(self::MU / $this->rInitialKm);
        $vTransfer1 = sqrt(self::MU * (2.0 / $this->rInitialKm - 1.0 / $this->transferSemiMajorAxis()));
        return $vTransfer1 - $v1;
    }

    /**
     * Second impulse (Delta-v2) applied at target orbit apoapsis (km/s).
     */
    public function deltaV2(): float
    {
        $v2 = sqrt(self::MU / $this->rTargetKm);
        $vTransfer2 = sqrt(self::MU * (2.0 / $this->rTargetKm - 1.0 / $this->transferSemiMajorAxis()));
        return $v2 - $vTransfer2;
    }

    /**
     * Total mission Delta-v budget (km/s).
     */
    public function totalDeltaV(): float
    {
        return abs($this->deltaV1()) + abs($this->deltaV2());
    }

    /**
     * Time of flight along transfer ellipse (seconds).
     */
    public function timeOfFlightSeconds(): float
    {
        return M_PI * sqrt(($this->transferSemiMajorAxis() ** 3) / self::MU);
    }
}

<?php

declare(strict_types=1);

namespace Astro\Constellation;

use Astro\Coordinate\KeplerianElements;

final readonly class WalkerDeltaConstellation
{
    /**
     * @param int $totalSatellites T: Total satellites in constellation
     * @param int $planes P: Number of orbital planes
     * @param int $phasing F: Inter-plane phasing parameter (0 to P-1)
     * @param float $altitudeKm Orbital altitude above Earth's surface
     * @param float $inclinationDeg Orbit inclination
     */
    public function __construct(
        public int $totalSatellites,
        public int $planes,
        public int $phasing,
        public float $altitudeKm = 700.0,
        public float $inclinationDeg = 53.0
    ) {
        if ($this->totalSatellites \%$this->planes !== 0) {
            throw new \InvalidArgumentException('Total satellites T must be divisible by planes P.');
        }
    }

    /**
     * Generate initial Keplerian elements for every satellite in the constellation.
     *
     * @return array<string, KeplerianElements>
     */
    public function generateSatellites(): array
    {
        $satellites = [];$satsPerPlane = (int)($this->totalSatellites / $this->planes);
        $semiMajorAxis = 6378.137 +$this->altitudeKm;

        for ($p = 0; $p <$this->planes; $p++) {$raan = fmod($p * (360.0 / $this->planes), 360.0);

            for ($s = 0; $s < $satsPerPlane; $s++) {
                $inPlaneAnomaly =$s * (360.0 / $satsPerPlane);$planePhaseOffset = $p * ($this->phasing * 360.0 / $this->totalSatellites);$meanAnomaly = fmod($inPlaneAnomaly +$planePhaseOffset, 360.0);

                $satId = sprintf('SAT-P%02d-S%02d', $p + 1,$s + 1);
                $satellites[$satId] = new KeplerianElements(
                    $semiMajorAxis, 0.0005, // near circular$this->inclinationDeg,
                    $raan, 0.0,$meanAnomaly
                );
            }
        }

        return $satellites;
    }
}

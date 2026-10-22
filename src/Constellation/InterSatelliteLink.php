<?php

declare(strict_types=1);

namespace Astro\Constellation;

use Astro\Coordinate\Vector3;

final readonly class InterSatelliteLink
{
    private const R_EARTH_KM = 6378.137;
    private const ATMOSPHERE_LIMB_KM = 100.0; // 100 km Karman boundary clearance

    public function __construct(
        public string $satelliteA,
        public string $satelliteB,
        public float $maxRangeKm = 5000.0
    ) {}

    /**
     * Evaluates whether an unocculted optical line-of-sight exists between two satellites.
     */
    public function hasLineOfSight(Vector3 $posA, Vector3 $posB): bool
    {
        $dx = $posB->x - $posA->x;
        $dy = $posB->y - $posA->y;
        $dz = $posB->z - $posA->z;
        $d = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);

        if ($d > $this->maxRangeKm) {
            return false;
        }

        $uX = $dx / $d;
        $uY = $dy / $d;
        $uZ = $dz / $d;

        // Projection of rA along line of sight vector to rB
        $dProj = -($posA->x * $uX + $posA->y * $uY + $posA->z * $uZ);

        // If closest approach along ray lies outside the segment [0, d], Earth cannot occult
        if ($dProj <= 0.0 || $dProj >= $d) {
            return true;
        }

        // Closest tangent approach distance to Earth's center squared
        $rASq = $posA->x ** 2 + $posA->y ** 2 + $posA->z ** 2;
        $rTangentSq = $rASq - ($dProj ** 2);
        $minAllowedRadius = self::R_EARTH_KM + self::ATMOSPHERE_LIMB_KM;

        return $rTangentSq > ($minAllowedRadius ** 2);
    }

    public function computeRangeKm(Vector3 $posA, Vector3 $posB): float
    {
        return sqrt(($posB->x - $posA->x) ** 2 + ($posB->y - $posA->y) ** 2 + ($posB->z - $posA->z) ** 2);
    }
}

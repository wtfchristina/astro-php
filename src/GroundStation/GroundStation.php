<?php

declare(strict_types=1);

namespace Astro\GroundStation;

use Astro\Coordinate\Vector3;

final readonly class GroundStation
{
    private const WGS84_A = 6378.137;
    private const WGS84_E2 = 0.00669437999014;

    public function __construct(
        public string $name,
        public float $latitudeDeg,
        public float $longitudeDeg,
        public float $altitudeKm = 0.0,
        public float $minElevationDeg = 10.0
    ) {}

    public function toEcef(): Vector3
    {
        $latRad = deg2rad($this->latitudeDeg);
        $lonRad = deg2rad($this->longitudeDeg);

        $n = self::WGS84_A / sqrt(1.0 - self::WGS84_E2 * (sin($latRad) ** 2));

        $x = ($n + $this->altitudeKm) * cos($latRad) * cos($lonRad);
        $y = ($n + $this->altitudeKm) * cos($latRad) * sin($lonRad);
        $z = ($n * (1.0 - self::WGS84_E2) + $this->altitudeKm) * sin($latRad);

        return new Vector3($x, $y, $z);
    }
}

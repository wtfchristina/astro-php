<?php

declare(strict_types=1);

namespace Astro\GroundStation;

final readonly class PassWindow
{
    public function __construct(
        public string $stationName,
        public string $aosEpoch,
        public string $tcaEpoch,
        public string $losEpoch,
        public float $durationSeconds,
        public float $maxElevationDeg,
        public float $minRangeKm
    ) {}
}

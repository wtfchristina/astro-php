<?php

declare(strict_types=1);

namespace Astro\GroundStation;

final readonly class TopocentricCoordinate
{
    public function __construct(
        public float $azimuthDeg,
        public float $elevationDeg,
        public float $rangeKm
    ) {}
}

<?php

declare(strict_types=1);

namespace Astro\Coordinate;

final readonly class GeodeticCoordinate
{
    public function __construct(
        public float $latitudeDeg,
        public float $longitudeDeg,
        public float $altitudeKm
    ) {}
}

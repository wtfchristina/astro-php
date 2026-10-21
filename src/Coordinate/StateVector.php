<?php

declare(strict_types=1);

namespace Astro\Coordinate;

final readonly class StateVector
{
    public function __construct(
        public Vector3 $position, // ECI coordinates (meters)
        public Vector3 $velocity, // ECI velocity (meters/second)
    ) {}
}

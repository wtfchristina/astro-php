<?php

declare(strict_types=1);

namespace Astro\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

interface ForceModelInterface
{
    public function computeAcceleration(StateVector $state, float $epochSeconds): Vector3;
}

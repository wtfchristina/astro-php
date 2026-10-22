<?php

declare(strict_types=1);

namespace Astro\Force;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

final readonly class CompositeForceModel implements ForceModelInterface
{
    /** @var list<ForceModelInterface> */
    private array $forces;

    public function __construct(ForceModelInterface ...$forces)
    {
        $this->forces = $forces;
    }

    public function computeAcceleration(StateVector $state, float $epochOffsetSeconds = 0.0): Vector3
    {
        $ax = 0.0;
        $ay = 0.0;
        $az = 0.0;

        foreach ($this->forces as $force) {
            $a = $force->computeAcceleration($state, $epochOffsetSeconds);
            $ax += $a->x;
            $ay += $a->y;
            $az += $a->z;
        }

        return new Vector3($ax, $ay, $az);
    }
}

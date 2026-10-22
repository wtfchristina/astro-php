<?php

declare(strict_types=1);

namespace Astro\Maneuver;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;

final readonly class ImpulsiveBurn
{
    public function __construct(
        public string $name,
        public float $executionOffsetSeconds,
        public Vector3 $deltaVKmS
    ) {}

    /**
     * Applies instantaneous velocity increment if current step crosses the burn epoch.
     */
    public function applyIfTriggered(StateVector $state, float $tCurrent, float $dt): StateVector
    {
        if ($tCurrent <= $this->executionOffsetSeconds && ($tCurrent + $dt) > $this->executionOffsetSeconds) {
            $newVelocity = new Vector3(
                $state->velocity->x + $this->deltaVKmS->x,
                $state->velocity->y + $this->deltaVKmS->y,
                $state->velocity->z + $this->deltaVKmS->z
            );
            return new StateVector($state->position, $newVelocity);
        }

        return $state;
    }
}

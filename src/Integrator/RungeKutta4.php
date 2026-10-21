<?php

declare(strict_types=1);

namespace Astro\Integrator;

use Astro\Coordinate\StateVector;
use Astro\Force\ForceModelInterface;

final readonly class RungeKutta4
{
    public function __construct(
        private ForceModelInterface $forceModel
    ) {}

    public function step(StateVector $state, float $epoch, float $dt): StateVector
    {
        $v1 = $state->velocity;
        $a1 = $this->forceModel->computeAcceleration($state, $epoch);

        $s2 = new StateVector(
            position: $state->position->add($v1->scale(0.5 * $dt)),
            velocity: $state->velocity->add($a1->scale(0.5 * $dt))
        );
        $v2 = $s2->velocity;
        $a2 = $this->forceModel->computeAcceleration($s2, $epoch + (0.5 * $dt));

        $s3 = new StateVector(
            position: $state->position->add($v2->scale(0.5 * $dt)),
            velocity: $state->velocity->add($a2->scale(0.5 * $dt))
        );
        $v3 = $s3->velocity;
        $a3 = $this->forceModel->computeAcceleration($s3, $epoch + (0.5 * $dt));

        $s4 = new StateVector(
            position: $state->position->add($v3->scale($dt)),
            velocity: $state->velocity->add($a3->scale($dt))
        );
        $v4 = $s4->velocity;
        $a4 = $this->forceModel->computeAcceleration($s4, $epoch + $dt);

        $posDelta = $v1->add($v2->scale(2.0))->add($v3->scale(2.0))->add($v4)->scale($dt / 6.0);
        $velDelta = $a1->add($a2->scale(2.0))->add($a3->scale(2.0))->add($a4)->scale($dt / 6.0);

        return new StateVector(
            position: $state->position->add($posDelta),
            velocity: $state->velocity->add($velDelta)
        );
    }
}

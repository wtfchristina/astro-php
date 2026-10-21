<?php

declare(strict_types=1);

namespace Astro\Coordinate;

use Astro\Force\EarthGravityModel;

final readonly class KeplerianElements
{
    public function __construct(
        public float $semiMajorAxis,
        public float $eccentricity,
        public float $inclination,
        public float $raan,
        public float $argPerigee,
        public float $meanAnomaly,
    ) {}

    public function toStateVector(float $mu = EarthGravityModel::MU): StateVector
    {
        $e = $this->eccentricity;
        $m = fmod($this->meanAnomaly, 2.0 * M_PI);
        if ($m < 0.0) {
            $m += 2.0 * M_PI;
        }

        $eccAnomaly = $m;
        for ($iter = 0; $iter < 15; $iter++) {
            $f = $eccAnomaly - ($e * sin($eccAnomaly)) - $m;
            $fPrime = 1.0 - ($e * cos($eccAnomaly));
            $delta = $f / $fPrime;
            $eccAnomaly -= $delta;
            if (abs($delta) < 1e-12) {
                break;
            }
        }

        $sinNu = (sqrt(1.0 - ($e ** 2)) * sin($eccAnomaly)) / (1.0 - ($e * cos($eccAnomaly)));
        $cosNu = (cos($eccAnomaly) - $e) / (1.0 - ($e * cos($eccAnomaly)));
        $trueAnomaly = atan2($sinNu, $cosNu);

        $r = $this->semiMajorAxis * (1.0 - ($e * cos($eccAnomaly)));
        $p = $this->semiMajorAxis * (1.0 - ($e ** 2));
        $sqrtMuOverP = sqrt($mu / $p);

        $posPqw = new Vector3(
            $r * cos($trueAnomaly),
            $r * sin($trueAnomaly),
            0.0
        );

        $velPqw = new Vector3(
            -$sqrtMuOverP * sin($trueAnomaly),
            $sqrtMuOverP * ($e + cos($trueAnomaly)),
            0.0
        );

        return new StateVector(
            position: $this->transformPqwToEci($posPqw),
            velocity: $this->transformPqwToEci($velPqw)
        );
    }

    private function transformPqwToEci(Vector3 $vec): Vector3
    {
        $cosO = cos($this->raan);
        $sinO = sin($this->raan);
        $cosW = cos($this->argPerigee);
        $sinW = sin($this->argPerigee);
        $cosI = cos($this->inclination);
        $sinI = sin($this->inclination);

        $r11 = ($cosO * $cosW) - ($sinO * $sinW * $cosI);
        $r12 = (-$cosO * $sinW) - ($sinO * $cosW * $cosI);
        $r21 = ($sinO * $cosW) + ($cosO * $sinW * $cosI);
        $r22 = (-$sinO * $sinW) + ($cosO * $cosW * $cosI);
        $r31 = $sinW * $sinI;
        $r32 = $cosW * $sinI;

        return new Vector3(
            x: ($r11 * $vec->x) + ($r12 * $vec->y),
            y: ($r21 * $vec->x) + ($r22 * $vec->y),
            z: ($r31 * $vec->x) + ($r32 * $vec->y),
        );
    }
}

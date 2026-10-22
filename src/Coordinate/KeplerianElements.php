<?php

declare(strict_types=1);

namespace Astro\Coordinate;

final readonly class KeplerianElements
{
    private const MU = 398600.4418; // km^3/s^2

    public function __construct(
        public float $semiMajorAxis,
        public float $eccentricity,
        public float $inclination,
        public float $raan,
        public float $argPerigee,
        public float $meanAnomaly
    ) {}

    public function toStateVector(float $mu = self::MU): StateVector
    {
        $incRad = deg2rad($this->inclination);
        $raanRad = deg2rad($this->raan);
        $argRad = deg2rad($this->argPerigee);
        $nuRad = deg2rad($this->meanAnomaly); // Circular/near-circular approx

        $p = $this->semiMajorAxis * (1.0 - $this->eccentricity ** 2);
        $r = $p / (1.0 + $this->eccentricity * cos($nuRad));

        // Position & velocity in orbital plane (PQW frame)
        $rPqw = new Vector3($r * cos($nuRad), $r * sin($nuRad), 0.0);
        $vFactor = sqrt($mu / $p);
        $vPqw = new Vector3(-$vFactor * sin($nuRad), $vFactor * ($this->eccentricity + cos($nuRad)), 0.0);

        // Transformation matrix components from PQW to ECI
        $cosO = cos($raanRad);
        $sinO = sin($raanRad);
        $cosW = cos($argRad);
        $sinW = sin($argRad);
        $cosI = cos($incRad);
        $sinI = sin($incRad);

        $px = ($cosO * $cosW - $sinO * $sinW * $cosI) * $rPqw->x + (-$cosO * $sinW - $sinO * $cosW * $cosI) * $rPqw->y;
        $py = ($sinO * $cosW + $cosO * $sinW * $cosI) * $rPqw->x + (-$sinO * $sinW + $cosO * $cosW * $cosI) * $rPqw->y;
        $pz = ($sinW * $sinI) * $rPqw->x + ($cosW * $sinI) * $rPqw->y;

        $vx = ($cosO * $cosW - $sinO * $sinW * $cosI) * $vPqw->x + (-$cosO * $sinW - $sinO * $cosW * $cosI) * $vPqw->y;
        $vy = ($sinO * $cosW + $cosO * $sinW * $cosI) * $vPqw->x + (-$sinO * $sinW + $cosO * $cosW * $cosI) * $vPqw->y;
        $vz = ($sinW * $sinI) * $vPqw->x + ($cosW * $sinI) * $vPqw->y;

        return new StateVector(new Vector3($px, $py, $pz), new Vector3($vx, $vy, $vz));
    }

    public static function fromStateVector(StateVector $state, float $mu = self::MU): self
    {
        $rVec = $state->position;
        $vVec = $state->velocity;
        $r = $rVec->magnitude();
        $v = $vVec->magnitude();

        // Angular momentum vector: h = r x v
        $hx = $rVec->y * $vVec->z - $rVec->z * $vVec->y;
        $hy = $rVec->z * $vVec->x - $rVec->x * $vVec->z;
        $hz = $rVec->x * $vVec->y - $rVec->y * $vVec->x;
        $h = sqrt($hx ** 2 + $hy ** 2 + $hz ** 2);

        // Node vector: n = k x h = [-hy, hx, 0]
        $nx = -$hy;
        $ny = $hx;
        $n = sqrt($nx ** 2 + $ny ** 2);

        // Eccentricity vector: e = (1/mu) * [(v^2 - mu/r)r - (r.v)v]
        $rDotV = $rVec->x * $vVec->x + $rVec->y * $vVec->y + $rVec->z * $vVec->z;
        $v2MinusMuOverR = ($v ** 2) - ($mu / $r);

        $ex = (1.0 / $mu) * ($v2MinusMuOverR * $rVec->x - $rDotV * $vVec->x);
        $ey = (1.0 / $mu) * ($v2MinusMuOverR * $rVec->y - $rDotV * $vVec->y);
        $ez = (1.0 / $mu) * ($v2MinusMuOverR * $rVec->z - $rDotV * $vVec->z);
        $eccentricity = sqrt($ex ** 2 + $ey ** 2 + $ez ** 2);

        // Semi-major axis: 1/a = 2/r - v^2/mu
        $invA = (2.0 / $r) - (($v ** 2) / $mu);
        $semiMajorAxis = abs($invA) > 1e-12 ? (1.0 / $invA) : INF;

        // Inclination
        $inclinationDeg = rad2deg(acos(max(-1.0, min(1.0, $hz / $h))));

        // Right Ascension of Ascending Node (RAAN)
        $raanDeg = 0.0;
        if ($n > 1e-9) {
            $raanDeg = rad2deg(atan2($ny, $nx));
            if ($raanDeg < 0.0) {
                $raanDeg += 360.0;
            }
        }

        // Argument of Periapsis
        $argPerigeeDeg = 0.0;
        if ($n > 1e-9 && $eccentricity > 1e-6) {
            $nDotE = $nx * $ex + $ny * $ey;
            $argPerigeeDeg = rad2deg(acos(max(-1.0, min(1.0, $nDotE / ($n * $eccentricity)))));
            if ($ez < 0.0) {
                $argPerigeeDeg = 360.0 - $argPerigeeDeg;
            }
        }

        // True Anomaly / Mean Anomaly
        $meanAnomalyDeg = 0.0;
        if ($eccentricity > 1e-6) {
            $eDotR = $ex * $rVec->x + $ey * $rVec->y + $ez * $rVec->z;
            $meanAnomalyDeg = rad2deg(acos(max(-1.0, min(1.0, $eDotR / ($eccentricity * $r)))));
            if ($rDotV < 0.0) {
                $meanAnomalyDeg = 360.0 - $meanAnomalyDeg;
            }
        }

        return new self(
            $semiMajorAxis,
            $eccentricity,
            $inclinationDeg,
            $raanDeg,
            $argPerigeeDeg,
            $meanAnomalyDeg
        );
    }
}

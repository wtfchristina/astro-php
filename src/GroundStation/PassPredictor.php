<?php

declare(strict_types=1);

namespace Astro\GroundStation;

use Astro\Coordinate\Vector3;

final class PassPredictor
{
    public function calculateLookAngles(GroundStation $station, Vector3 $satEcef): TopocentricCoordinate
    {
        $stationEcef = $station->toEcef();

        // Slant range vector in ECEF
        $dx = $satEcef->x - $stationEcef->x;
        $dy = $satEcef->y - $stationEcef->y;
        $dz = $satEcef->z - $stationEcef->z;

        $range = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);
        if ($range < 1e-9) {
            return new TopocentricCoordinate(0.0, 90.0, 0.0);
        }

        $phi = deg2rad($station->latitudeDeg);
        $lambda = deg2rad($station->longitudeDeg);

        $sinPhi = sin($phi);
        $cosPhi = cos($phi);
        $sinLambda = sin($lambda);
        $cosLambda = cos($lambda);

        // Rotate delta vector into South-East-Zenith (SEZ) frame
        $south = $sinPhi * $cosLambda * $dx + $sinPhi * $sinLambda * $dy - $cosPhi * $dz;
        $east  = -$sinLambda * $dx + $cosLambda * $dy;
        $zenith = $cosPhi * $cosLambda * $dx + $cosPhi * $sinLambda * $dy + $sinPhi * $dz;

        // Elevation angle: arcsin(Z / rho)
        $elevationRad = asin(max(-1.0, min(1.0, $zenith / $range)));

        // Azimuth measured clockwise from True North: atan2(East, North) where North = -South
        $azimuthRad = atan2($east, -$south);
        if ($azimuthRad < 0.0) {
            $azimuthRad += 2.0 * M_PI;
        }

        return new TopocentricCoordinate(
            rad2deg($azimuthRad),
            rad2deg($elevationRad),
            $range
        );
    }
}

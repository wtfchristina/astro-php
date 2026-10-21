<?php

declare(strict_types=1);

namespace Astro\Coordinate;

final class Wgs84Transformer
{
    private const WGS84_A = 6378.137;           // Semi-major axis (km)
    private const WGS84_F = 1.0 / 298.257223563; // Flattening
    private const WGS84_B = 6356.7523142;       // Semi-minor axis (km)
    private const WGS84_E2 = 0.00669437999014;  // First eccentricity squared
    private const WGS84_E_PRIME2 = 0.00673949674228; // Second eccentricity squared
    private const OMEGA_EARTH = 7.2921159e-5;   // rad/s

    /**
     * Convert ECI coordinates to ECEF by applying GMST rotation.
     */
    public function eciToEcef(Vector3 $posEci, float $gmstRad): Vector3
    {
        $cosG = cos($gmstRad);
        $sinG = sin($gmstRad);

        $xEcef =  $cosG * $posEci->x + $sinG * $posEci->y;
        $yEcef = -$sinG * $posEci->x + $cosG * $posEci->y;
        $zEcef =  $posEci->z;

        return new Vector3($xEcef, $yEcef, $zEcef);
    }

    /**
     * Convert ECEF coordinates to WGS-84 Geodetic (Lat, Lon, Alt) via Bowring's method.
     */
    public function ecefToGeodetic(Vector3 $posEcef): GeodeticCoordinate
    {
        $x = $posEcef->x;
        $y = $posEcef->y;
        $z = $posEcef->z;

        $p = sqrt($x ** 2 + $y ** 2);
        $lonRad = atan2($y, $x);

        if ($p < 1e-6) {
            $latRad = $z >= 0.0 ? (M_PI / 2.0) : (-M_PI / 2.0);
            $altKm = abs($z) - self::WGS84_B;
            return new GeodeticCoordinate(
                rad2deg($latRad),
                rad2deg($lonRad),
                $altKm
            );
        }

        // Bowring's closed-form algorithm
        $theta = atan2($z * self::WGS84_A, $p * self::WGS84_B);
        $latRad = atan2(
            $z + self::WGS84_E_PRIME2 * self::WGS84_B * (sin($theta) ** 3),
            $p - self::WGS84_E2 * self::WGS84_A * (cos($theta) ** 3)
        );

        $n = self::WGS84_A / sqrt(1.0 - self::WGS84_E2 * (sin($latRad) ** 2));
        $altKm = ($p / cos($latRad)) - $n;

        return new GeodeticCoordinate(
            rad2deg($latRad),
            rad2deg($lonRad),
            $altKm
        );
    }
}

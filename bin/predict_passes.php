<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Coordinate\Wgs84Transformer;
use Astro\Force\EarthGravityModel;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassPredictor;
use Astro\Integrator\RungeKutta4;

echo "========================================================================\n";
echo " AstroPHP: Ground Station Access Window & Elevation Profiler\n";
echo "========================================================================\n\n";

$stations = [
    new GroundStation('PHX - Phoenix Ground Station', 33.4484, -112.0740, 0.331, 10.0),
    new GroundStation('SGS - Svalbard Satellite Station', 78.2298, 15.4078, 0.400, 5.0),
];

// LEO SSO 700 km altitude, 98.2° inclination, RAAN 45°
$orbit = new KeplerianElements(
    EarthGravityModel::R_EARTH + 700.0,
    0.001,
    98.2,
    45.0,
    0.0,
    0.0
);

$state = $orbit->toStateVector();
$forceModel = new EarthGravityModel();
$integrator = new RungeKutta4($forceModel);
$predictor = new PassPredictor();
$transformer = new Wgs84Transformer();

$startEpoch = time();
$durationSec = 86400; // 24-hour simulation horizon
$dt = 10.0;           // 10-second propagation step

// Standard IAU GMST calculation including full diurnal Earth rotation
$calcGmstRad = function (int $unixTimestamp): float {
    $jd = ($unixTimestamp / 86400.0) + 2440587.5;
    $d = $jd - 2451545.0; // Days since J2000.0
    // GMST in degrees = 280.46061837 + 360.98564736629 * d
    $gmstDeg = fmod(280.46061837 + 360.98564736629 * $d, 360.0);
    if ($gmstDeg < 0.0) {
        $gmstDeg += 360.0;
    }
    return deg2rad($gmstDeg);
};

// Numerical Propagation & Trajectory Cache
$trajectory = [];
$simTime = 0.0;
$currentState = $state;

while ($simTime <= $durationSec) {
    $currentEpoch = $startEpoch + (int)$simTime;
    $gmst = $calcGmstRad($currentEpoch);
    $ecefPos = $transformer->eciToEcef($currentState->position, $gmst);

    $trajectory[] = [
        'epoch' => $currentEpoch,
        'ecef'  => $ecefPos,
    ];

    $currentState = $integrator->step($currentState, $simTime, $dt);
    $simTime += $dt;
}

foreach ($stations as $station) {
    printf("Target Facility: %s\n", $station->name);
    printf("Location: %.4f° Lat, %.4f° Lon, %.3f km Alt | Mask: %.1f°\n",
        $station->latitudeDeg,
        $station->longitudeDeg,
        $station->altitudeKm,
        $station->minElevationDeg
    );
    echo "------------------------------------------------------------------------\n";

    $passes = [];
    $inPass = false;
    $aosEpoch = 0;
    $maxElev = 0.0;

    foreach ($trajectory as $point) {
        $look = $predictor->calculateLookAngles($station, $point['ecef']);
        $elevation = $look->elevationDeg; // Directly use elevation in degrees

        if ($elevation >= $station->minElevationDeg) {
            if (!$inPass) {
                $inPass = true;
                $aosEpoch = $point['epoch'];
                $maxElev = $elevation;
            } elseif ($elevation > $maxElev) {
                $maxElev = $elevation;
            }
        } else {
            if ($inPass) {
                $inPass = false;
                $losEpoch = $point['epoch'];
                $duration = $losEpoch - $aosEpoch;
                if ($duration >= 60) {
                    $passes[] = [
                        'aosEpoch'     => $aosEpoch,
                        'losEpoch'     => $losEpoch,
                        'duration'     => $duration,
                        'maxElevation' => $maxElev,
                    ];
                }
            }
        }
    }

    if (empty($passes)) {
        echo " No contact windows predicted within the 24-hour horizon.\n\n";
        continue;
    }

    printf("%-22s  %-22s  %-12s  %-10s\n", "AOS (UTC)", "LOS (UTC)", "Duration", "Max Elev");
    foreach (array_slice($passes, 0, 6) as $pass) {
        printf(
            "%-22s  %-22s  %4d sec      %5.1f°\n",
            gmdate('Y-m-d H:i:s\Z', $pass['aosEpoch']),
            gmdate('Y-m-d H:i:s\Z', $pass['losEpoch']),
            $pass['duration'],
            $pass['maxElevation']
        );
    }
    echo "\n";
}

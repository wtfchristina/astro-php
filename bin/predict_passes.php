<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Coordinate\Vector3;
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
$dt = 15.0;           // 15-second integration steps

// IAU-82 GMST calculation function (returns radians)
$calcGmstRad = function (int $unixTimestamp): float {
    $jd = ($unixTimestamp / 86400.0) + 2440587.5;
    $t = ($jd - 2451545.0) / 36525.0;
    $thetaSec = 24110.54841 + 8640184.812866 * $t + 0.093104 * ($t ** 2) - 0.0000062 * ($t ** 3);
    $thetaDeg = fmod($thetaSec / 240.0, 360.0);
    if ($thetaDeg < 0) {
        $thetaDeg += 360.0;
    }
    return deg2rad($thetaDeg);
};

// Numerical Propagation Loop
$trajectory = [];
$simTime = 0.0;
$currentState = $state;

while ($simTime <= $durationSec) {
    $currentEpoch = $startEpoch + (int)$simTime;
    $gmst = $calcGmstRad($currentEpoch);
    $ecefPos = $transformer->eciToEcef($currentState->position, $gmst);

    $trajectory[] = [
        'epoch' => $currentEpoch,
        'ecef' => $ecefPos,
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
        $rawElev = is_array($look) 
            ? ($look['elevation'] ?? $look['elevationDeg'] ?? 0.0) 
            : ($look->elevation ?? $look->elevationDeg ?? 0.0);

        // Standardize to degrees if returned in radians
        $elevation = abs($rawElev) <= M_PI ? rad2deg($rawElev) : $rawElev;

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
                        'aosEpoch' => $aosEpoch,
                        'losEpoch' => $losEpoch,
                        'duration' => $duration,
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

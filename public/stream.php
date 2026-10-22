<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\AtmosphericDrag;
use Astro\Force\CompositeForceModel;
use Astro\Force\EarthGravityModel;
use Astro\Force\SolarRadiationPressure;
use Astro\Integrator\RungeKutta4;

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (ob_get_level() > 0) {
    ob_end_clean();
}

$elements = new KeplerianElements(
    semiMajorAxisKm: EarthGravityModel::R_EARTH + 700.0,
    eccentricity: 0.0012,
    inclinationDeg: 98.2,
    raanDeg: 45.0,
    argPerigeeDeg: 0.0,
    meanAnomalyDeg: 0.0
);

$state = $elements->toStateVector();
$forceModel = new CompositeForceModel(
    new EarthGravityModel(),
    new AtmosphericDrag(0.015),
    new SolarRadiationPressure(1.3, 0.02)
);
$integrator = new RungeKutta4($forceModel);

$simTime = 0.0;
$dt = 1.0; // 1-second physics step per tick

while (true) {
    if (connection_aborted()) {
        break;
    }

    $state = $integrator->step($state, $simTime, $dt);
    $simTime += $dt;

    $payload = [
        'simTimeSec' => $simTime,
        'epochIso' => gmdate('Y-m-d\TH:i:s\Z', time() + (int)$simTime),
        'positionKm' => [
            'x' => round($state->position->x, 4),
            'y' => round($state->position->y, 4),
            'z' => round($state->position->z, 4),
            'r' => round($state->position->magnitude(), 4),
            'altitudeKm' => round($state->position->magnitude() - EarthGravityModel::R_EARTH, 3),
        ],
        'velocityKmS' => [
            'vx' => round($state->velocity->x, 5),
            'vy' => round($state->velocity->y, 5),
            'vz' => round($state->velocity->z, 5),
            'v' => round($state->velocity->magnitude(), 5),
        ],
    ];

    echo "event: telemetry\n";
    echo 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";

    flush();
    usleep(100000); // 10 Hz telemetry tick
}

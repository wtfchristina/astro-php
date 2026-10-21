#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\EarthGravityModel;
use Astro\Integrator\RungeKutta4;
use Astro\Serializer\CzmlWriter;

echo "====================================================\n";
echo "   AstroPHP: Numerical Orbit Propagator (J2 Perturbed)\n";
echo "====================================================\n\n";

// 1. Mission setup: 700 km Sun-Synchronous Orbit (SSO)
$altitudeMeters = 700_000.0;
$semiMajorAxis = EarthGravityModel::R_EARTH + $altitudeMeters;
$eccentricity = 0.0012;
$inclinationDeg = 98.2;
$raanDeg = 45.0;

$orbit = new KeplerianElements(
    semiMajorAxis: $semiMajorAxis,
    eccentricity: $eccentricity,
    inclination: deg2rad($inclinationDeg),
    raan: deg2rad($raanDeg),
    argPerigee: deg2rad(0.0),
    meanAnomaly: deg2rad(0.0)
);

echo "Initial Orbit Parameters:\n";
echo " • Semi-Major Axis : " . number_format($semiMajorAxis / 1000, 2) . " km\n";
echo " • Altitude         : " . number_format($altitudeMeters / 1000, 2) . " km\n";
echo " • Inclination      : {$inclinationDeg}° (Sun-Synchronous)\n";
echo " • RAAN (Ω)         : {$raanDeg}°\n\n";

$initialState = $orbit->toStateVector();
$r0 = $initialState->position;
$v0 = $initialState->velocity;

echo "Cartesian State Vector at Epoch t=0 (ECI Frame):\n";
printf(" • Position [km]: [%10.3f, %10.3f, %10.3f] (Mag: %.2f km)\n", 
    $r0->x / 1000, $r0->y / 1000, $r0->z / 1000, $r0->magnitude() / 1000);
printf(" • Velocity [km/s]: [%7.3f, %7.3f, %7.3f] (Mag: %.3f km/s)\n\n", 
    $v0->x / 1000, $v0->y / 1000, $v0->z / 1000, $v0->magnitude() / 1000);

// 2. Numerical Integration Setup
$gravity = new EarthGravityModel();
$integrator = new RungeKutta4($gravity);

$orbitalPeriod = 2.0 * M_PI * sqrt(($semiMajorAxis ** 3) / EarthGravityModel::MU);
$totalDuration = $orbitalPeriod * 3.0; // Propagate 3 revolutions
$dt = 15.0; // 15-second integration timestep
$steps = (int)ceil($totalDuration / $dt);

echo "Propagating 3 revolutions (" . number_format($totalDuration, 0) . " s, dt={$dt}s)...\n";

$startTime = microtime(true);
$trajectory = [];
$state = $initialState;
$baseEpoch = time();

for ($i = 0; $i <= $steps; $i++) {
    $t = $i * $dt;
    $currentEpochStr = gmdate('Y-m-d\TH:i:s\Z', (int)($baseEpoch + $t));
    $trajectory[] = [
        'epoch' => $currentEpochStr,
        'state' => $state,
    ];
    $state = $integrator->step($state, (float)$t, $dt);
}

$elapsed = (microtime(true) - $startTime) * 1000;
printf("Propagation completed in %.2f ms (%d state points generated).\n\n", $elapsed, count($trajectory));

// 3. Export CZML for 3D visualization
$writer = new CzmlWriter();
$czmlContent = $writer->export('SAT-LEO-01', $trajectory);
$outFile = __DIR__ . '/../orbit.czml';
file_put_contents($outFile, $czmlContent);

echo "Trajectory written to: orbit.czml\n";
echo "Drop this file into https://sandcastle.cesium.com/ to render in 3D.\n";

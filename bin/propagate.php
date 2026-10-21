<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Force\TwoBodyGravity;
use Astro\Integrator\RungeKutta4;
use Astro\Serializer\CzmlWriter;

echo "=================================================\n";
echo " AstroPHP: Multi-Force Cowell Propagator (J2 + Drag)\n";
echo "=================================================\n\n";

// semiMajorAxis, eccentricity, inclination, raan, argPerigee, meanAnomaly
$elements = new KeplerianElements(
    7078.137,
    0.001,
    98.2,
    45.0,
    0.0,
    0.0
);

$initialState = $elements->toStateVector();

echo "Initial Orbit Parameters:\n";
echo " • Semi-Major Axis : 7,078.14 km\n";
echo " • Altitude         : 700.00 km\n";
echo " • Inclination      : 98.2° (Sun-Synchronous)\n";
echo " • RAAN (Ω)         : 45°\n\n";

$forces = [
    new TwoBodyGravity(),
    new GeopotentialJ2(),
    new AtmosphericDrag(0.015),
];

$integrator = new RungeKutta4($forces);

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$periodSeconds = 2.0 * M_PI * sqrt((7078.137 ** 3) / 398600.4418);
$totalDuration = $periodSeconds * 3.0; // 3 complete revolutions
$stepSize = 15.0; // 15s integration step

echo "Propagating 3 revolutions (" . number_format($totalDuration, 0) . " s, dt={$stepSize}s)...\n";
$startBenchmark = hrtime(true);

$trajectory = [];
$currentTime = 0.0;
$currentState = $initialState;

while ($currentTime <= $totalDuration) {
    $currentEpoch = $epoch->modify('+' . (int)round($currentTime) . ' seconds');
    $trajectory[] = [
        'epoch' => $currentEpoch->format('Y-m-d\TH:i:s\Z'),
        'state' => $currentState,
    ];

    $currentState = $integrator->step($currentState, $currentTime, $stepSize);
    $currentTime += $stepSize;
}

$elapsedMs = (hrtime(true) - $startBenchmark) / 1e6;
echo "Propagation completed in " . number_format($elapsedMs, 2) . " ms (" . count($trajectory) . " state points generated).\n\n";

$writer = new CzmlWriter();
$czmlJson = $writer->export('SAT-LEO-01', $trajectory);
file_put_contents(__DIR__ . '/../orbit.czml', $czmlJson);

echo "Trajectory written to: orbit.czml\n";

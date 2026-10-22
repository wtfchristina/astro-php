<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Force\SolarRadiationPressure;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassScheduleService;
use Astro\Integrator\RungeKutta4;
use Astro\Serializer\CzmlWriter;

echo "========================================================================\n";
echo " AstroPHP: Multi-Force Cowell Propagator (J2 + Drag + Solar Radiation)\n";
echo "========================================================================\n\n";

$stations = [
    new GroundStation('Phoenix Ground Station (PHX)', 33.4484, -112.0740, 0.331, 10.0),
    new GroundStation('Svalbard Satellite Station (SGS)', 78.2297, 15.4077, 0.400, 5.0),
    new GroundStation('Goldstone Deep Space Comm (GDS)', 35.4266, -116.8900, 1.000, 10.0),
];

$elements = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 0.0, 0.0);
$initialState = $elements->toStateVector();

$forces = [
    new GeopotentialJ2(),
    new AtmosphericDrag(0.015),
    new SolarRadiationPressure(1.3, 0.02),
];

$integrator = new RungeKutta4($forces);

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$periodSeconds = 2.0 * M_PI * sqrt((7078.137 ** 3) / 398600.4418);
$totalDuration = $periodSeconds * 3.0; // 3 complete revolutions
$stepSize = 10.0;

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
echo "Propagation completed in " . number_format($elapsedMs, 2) . " ms (" . count($trajectory) . " state points generated).\n";

$passService = new PassScheduleService();
$allPasses = [];
foreach ($stations as $station) {
    $passes = $passService->findPasses($station, $trajectory, 0.0);
    $allPasses = array_merge($allPasses, $passes);
}

$writer = new CzmlWriter();
$czmlJson = $writer->export('SAT-LEO-01', $trajectory, $stations, $allPasses, 0.0);
file_put_contents(__DIR__ . '/../orbit.czml', $czmlJson);

echo "Updated trajectory and ground tracks written to: orbit.czml\n";

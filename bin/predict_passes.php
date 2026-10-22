<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Force\TwoBodyGravity;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassScheduleService;
use Astro\Integrator\RungeKutta4;

echo "====================================================================\n";
echo " AstroPHP: Ground Station Contact Pass Predictor (AOS / TCA / LOS)\n";
echo "====================================================================\n\n";

$stations = [
    new GroundStation('Phoenix Ground Station (PHX)', 33.4484, -112.0740, 0.331, 10.0),
    new GroundStation('Svalbard Satellite Station (SGS)', 78.2297, 15.4077, 0.400, 5.0),
    new GroundStation('Goldstone Deep Space Comm (GDS)', 35.4266, -116.8900, 1.000, 10.0),
];

$elements = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 0.0, 0.0);
$initialState = $elements->toStateVector();
$forces = [new TwoBodyGravity(), new GeopotentialJ2(), new AtmosphericDrag(0.015)];
$integrator = new RungeKutta4($forces);

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$totalDuration = 86400.0; // 24-hour mission window
$stepSize = 10.0;          // 10s propagation fidelity

echo "Simulating 24-hour mission horizon (86,400s, dt=10s)...\n";
$startBenchmark = hrtime(true);

$trajectory = [];
$t = 0.0;
$state = $initialState;

while ($t <= $totalDuration) {
    $trajectory[] = [
        'epoch' => $epoch->modify('+' . (int)round($t) . ' seconds')->format('Y-m-d\TH:i:s\Z'),
        'state' => $state,
    ];
    $state = $integrator->step($state, $t, $stepSize);
    $t += $stepSize;
}

$simElapsedMs = (hrtime(true) - $startBenchmark) / 1e6;
echo "Propagation completed in " . number_format($simElapsedMs, 2) . " ms (" . count($trajectory) . " ephemeris steps).\n\n";

$passService = new PassScheduleService();

foreach ($stations as $station) {
    echo "--------------------------------------------------------------------\n";
    echo " Target Station: {$station->name} (Min El: {$station->minElevationDeg}°)\n";
    echo "--------------------------------------------------------------------\n";

    $passes = $passService->findPasses($station, $trajectory, 0.0);

    if (empty($passes)) {
        echo " No contact windows detected within the 24-hour simulation window.\n\n";
        continue;
    }

    printf(
        " %-20s | %-20s | %-20s | %-8s | %-7s | %-9s\n",
        'AOS (UTC)', 'TCA (UTC)', 'LOS (UTC)', 'Duration', 'Max El', 'Min Slant'
    );
    printf(" %s\n", str_repeat('-', 98));

    foreach ($passes as $p) {
        printf(
            " %-20s | %-20s | %-20s | %6.0fs | %5.1f°  | %7.1f km\n",
            $p->aosEpoch,
            $p->tcaEpoch,
            $p->losEpoch,
            $p->durationSeconds,
            $p->maxElevationDeg,
            $p->minRangeKm
        );
    }
    echo "\n";
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Coordinate\Vector3;
use Astro\Coordinate\Wgs84Transformer;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Force\EarthGravityModel;
use Astro\GroundStation\DopplerCalculator;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassScheduleService;
use Astro\Integrator\RungeKutta4;

echo "========================================================================================\n";
echo " AstroPHP: Ground Station Contact Pass & S-Band Doppler Analysis (AOS / TCA / LOS)\n";
echo "========================================================================================\n\n";

$stations = [
    new GroundStation('Phoenix Ground Station (PHX)', 33.4484, -112.0740, 0.331, 10.0),
    new GroundStation('Svalbard Satellite Station (SGS)', 78.2297, 15.4077, 0.400, 5.0),
    new GroundStation('Goldstone Deep Space Comm (GDS)', 35.4266, -116.8900, 1.000, 10.0),
];

$elements = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 0.0, 0.0);
$initialState = $elements->toStateVector();
$forces = [new EarthGravityModel(), new GeopotentialJ2(), new AtmosphericDrag(0.015)];
$integrator = new RungeKutta4($forces);

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$totalDuration = 86400.0;
$stepSize = 10.0;

echo "Propagating 24-hour mission window (86,400s, dt=10s)...\n";
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
echo "Propagation completed in " . number_format($simElapsedMs, 2) . " ms (" . count($trajectory) . " state vectors).\n\n";

$passService = new PassScheduleService();
$dopplerCalc = new DopplerCalculator();
$transformer = new Wgs84Transformer();
$carrierFrequencyHz = 2.2e9; // 2.2 GHz S-band carrier

foreach ($stations as $station) {
    echo "----------------------------------------------------------------------------------------\n";
    echo " Target: {$station->name} (Min El: {$station->minElevationDeg}°, Carrier: 2.2 GHz)\n";
    echo "----------------------------------------------------------------------------------------\n";

    $passes = $passService->findPasses($station, $trajectory, 0.0);

    if (empty($passes)) {
        echo " No contact windows detected within this 24-hour horizon.\n\n";
        continue;
    }

    printf(
        " %-20s | %-20s | %-8s | %-7s | %-9s | %-12s\n",
        'AOS (UTC)', 'LOS (UTC)', 'Duration', 'Max El', 'Min Slant', 'Max Doppler'
    );
    printf(" %s\n", str_repeat('-', 90));

    foreach ($passes as $p) {
        // Approximate peak Doppler magnitude using satellite orbital speed projection (~7.5 km/s max)
        $maxDopplerShift = $dopplerCalc->computeDopplerShift($carrierFrequencyHz, -7.0);

        printf(
            " %-20s | %-20s | %6.0fs | %5.1f°  | %7.1f km | ±%5.1f kHz\n",
            $p->aosEpoch,
            $p->losEpoch,
            $p->durationSeconds,
            $p->maxElevationDeg,
            $p->minRangeKm,
            abs($maxDopplerShift) / 1000.0
        );
    }
    echo "\n";
}

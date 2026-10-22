<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Integrator\RungeKutta4;

echo "====================================================================\n";
echo " AstroPHP: Osculating Orbital Elements & Secular Perturbation Analyzer\n";
echo "====================================================================\n\n";

$elements = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 0.0, 0.0);
$state = $elements->toStateVector();
$forces = [new GeopotentialJ2(), new AtmosphericDrag(0.015)];
$integrator = new RungeKutta4($forces);

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$totalDuration = 86400.0; // 24 hours
$stepSize = 60.0;

echo sprintf(" %-12s | %-12s | %-10s | %-10s | %-10s\n", 'Elapsed (hr)', 'Semi-Major (km)', 'Eccentricity', 'Inclination(°)', 'RAAN (Ω°)');
echo str_repeat('-', 68) . "\n";

$t = 0.0;
while ($t <= $totalDuration) {
    if (fmod($t, 14400.0) === 0.0) { // Log every 4 hours
        $osc = KeplerianElements::fromStateVector($state);
        printf(
            " %10.1fh  | %14.3f  | %10.6f  | %10.3f°   | %10.3f°\n",
            $t / 3600.0,
            $osc->semiMajorAxis,
            $osc->eccentricity,
            $osc->inclination,
            $osc->raan
        );
    }
    $state = $integrator->step($state, $t, $stepSize);
    $t += $stepSize;
}
echo "\n";

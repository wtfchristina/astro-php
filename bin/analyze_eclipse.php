<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Environment\ShadowModel;
use Astro\Environment\ShadowState;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Integrator\RungeKutta4;

echo "====================================================================\n";
echo " AstroPHP: Orbital Eclipse, Shadow Entry, & Solar Beta Analyzer\n";
echo "====================================================================\n\n";

$elements = new KeplerianElements(7078.137, 0.001, 98.2, 45.0, 0.0, 0.0);
$currentState = $elements->toStateVector();
$forces = [new GeopotentialJ2(), new AtmosphericDrag(0.015)];
$integrator = new RungeKutta4($forces);
$shadowModel = new ShadowModel();

// Epoch: Oct 21, 2026, 22:00:00 UTC (Julian Date ~ 2461335.41667)
$epochJd = 2461335.41667;
$sunVec = $shadowModel->getSunPositionEci($epochJd);
$betaAngle = $shadowModel->computeBetaAngleDeg($currentState, $sunVec);

echo "Orbit & Solar Ephemeris Parameters:\n";
echo " • Sun Vector Distance : " . number_format($sunVec->magnitude(), 0) . " km (1.0 AU)\n";
echo " • Solar Beta Angle (β): " . number_format($betaAngle, 2) . "°\n";
echo " • Period              : 5,926 s (98.7 min)\n\n";

$period = 5926.0; // ~1 complete orbit
$dt = 5.0;        // 5s resolution
$totalTime = $period;

$sunlightSeconds = 0.0;
$umbraSeconds = 0.0;
$penumbraSeconds = 0.0;
$t = 0.0;

while ($t <= $totalTime) {
    $state = $shadowModel->evaluateShadow($currentState->position, $sunVec);

    match ($state) {
        ShadowState::Sunlight => $sunlightSeconds += $dt,
        ShadowState::Umbra => $umbraSeconds += $dt,
        ShadowState::Penumbra => $penumbraSeconds += $dt,
    };

    $currentState = $integrator->step($currentState, $t, $dt);
    $t += $dt;
}

$totalEvaluated = $sunlightSeconds + $umbraSeconds + $penumbraSeconds;
$sunlightFraction = ($sunlightSeconds / $totalEvaluated) * 100.0;
$eclipseFraction = (($umbraSeconds + $penumbraSeconds) / $totalEvaluated) * 100.0;

echo "Orbital Lighting Duty Cycle (Single Revolution):\n";
echo " --------------------------------------------------------------------\n";
echo " • Daylight Duration   : " . number_format($sunlightSeconds / 60.0, 2) . " min (" . number_format($sunlightFraction, 1) . "%)\n";
echo " • Umbra Duration      : " . number_format($umbraSeconds / 60.0, 2) . " min\n";
echo " • Penumbra Duration   : " . number_format($penumbraSeconds / 60.0, 2) . " min\n";
echo " • Total Eclipse Time  : " . number_format(($umbraSeconds + $penumbraSeconds) / 60.0, 2) . " min (" . number_format($eclipseFraction, 1) . "%)\n\n";

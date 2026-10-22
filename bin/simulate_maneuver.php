<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Coordinate\KeplerianElements;
use Astro\Coordinate\Vector3;
use Astro\Force\AtmosphericDrag;
use Astro\Force\GeopotentialJ2;
use Astro\Integrator\RungeKutta4;
use Astro\Maneuver\HohmannTransfer;
use Astro\Maneuver\ImpulsiveBurn;
use Astro\Serializer\CzmlWriter;

echo "====================================================================\n";
echo " AstroPHP: Orbital Maneuver & Hohmann Delta-V Simulation Engine\n";
echo "====================================================================\n\n";

$r1 = 6378.137 + 700.0;   // 700 km initial circular altitude
$r2 = 6378.137 + 1200.0;  // 1,200 km target circular altitude

$transfer = new HohmannTransfer($r1, $r2);
$dv1 = $transfer->deltaV1();
$dv2 = $transfer->deltaV2();
$tof = $transfer->timeOfFlightSeconds();

echo "Maneuver Profile (LEO Orbit Raise):\n";
echo " • Initial Altitude : 700.00 km (r = " . number_format($r1, 2) . " km)\n";
echo " • Target Altitude  : 1,200.00 km (r = " . number_format($r2, 2) . " km)\n";
echo " • Δv1 (Injection)  : " . number_format($dv1 * 1000.0, 2) . " m/s\n";
echo " • Δv2 (Circularize): " . number_format($dv2 * 1000.0, 2) . " m/s\n";
echo " • Total Δv Budget  : " . number_format($transfer->totalDeltaV() * 1000.0, 2) . " m/s\n";
echo " • Transfer TOF     : " . number_format($tof / 60.0, 2) . " min (" . number_format($tof, 1) . " s)\n\n";

$initialElements = new KeplerianElements(7078.137, 0.0001, 98.2, 45.0, 0.0, 0.0);
$currentState = $initialElements->toStateVector();

$forces = [new GeopotentialJ2(), new AtmosphericDrag(0.015)];
$integrator = new RungeKutta4($forces);

$burn1Epoch = 1200.0; // Execute burn 1 after 20 minutes in orbit
$burn2Epoch = $burn1Epoch + $tof;

// Prograde unit tangent vector for near-circular orbit at burn1: v_hat = v / |v|
$vMag = $currentState->velocity->magnitude();
$vHat = new Vector3(
    $currentState->velocity->x / $vMag,
    $currentState->velocity->y / $vMag,
    $currentState->velocity->z / $vMag
);

$burns = [
    new ImpulsiveBurn('Burn1_Injection', $burn1Epoch, new Vector3($vHat->x * $dv1, $vHat->y * $dv1, $vHat->z * $dv1)),
];

$epoch = new DateTimeImmutable('2026-10-21T22:00:00Z');
$totalTime = $burn2Epoch + 3600.0; // Propagate past completion
$stepSize = 10.0;

echo "Propagating flight profile through orbital insertion (" . number_format($totalTime, 0) . " s, dt={$stepSize}s)...\n";
$startBenchmark = hrtime(true);

$trajectory = [];
$t = 0.0;

while ($t <= $totalTime) {
    foreach ($burns as $burn) {
        $currentState = $burn->applyIfTriggered($currentState, $t, $stepSize);
    }

    $trajectory[] = [
        'epoch' => $epoch->modify('+' . (int)round($t) . ' seconds')->format('Y-m-d\TH:i:s\Z'),
        'state' => $currentState,
    ];

    $currentState = $integrator->step($currentState, $t, $stepSize);
    $t += $stepSize;
}

$simElapsedMs = (hrtime(true) - $startBenchmark) / 1e6;
echo "Propagation completed in " . number_format($simElapsedMs, 2) . " ms (" . count($trajectory) . " state points).\n";

$finalAlt = $currentState->position->magnitude() - 6378.137;
echo "Final Post-Burn Altitude: " . number_format($finalAlt, 2) . " km\n\n";

$writer = new CzmlWriter();
$czmlJson = $writer->export('SAT-LEO-01', $trajectory, [], [], 0.0);
file_put_contents(__DIR__ . '/../orbit.czml', $czmlJson);

echo "Updated maneuver trajectory written to: orbit.czml\n";

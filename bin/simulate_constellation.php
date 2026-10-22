<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Constellation\InterSatelliteLink;
use Astro\Constellation\WalkerDeltaConstellation;
use Astro\Force\AtmosphericDrag;
use Astro\Force\CompositeForceModel;
use Astro\Force\EarthGravityModel;
use Astro\Integrator\RungeKutta4;

echo "====================================================================\n";
echo " AstroPHP: Walker-Delta Constellation & Laser Cross-Link Simulator\n";
echo "====================================================================\n\n";

$walker = new WalkerDeltaConstellation(12, 3, 1, 700.0, 53.0);
$satellites = $walker->generateSatellites();

echo "Walker-Delta Architecture: 12/3/1 (T=12, P=3, F=1, Alt=700 km, Inc=53°)\n";
echo "Active Constellation Nodes: " . count($satellites) . " Spacecraft\n\n";

$forceModel = new CompositeForceModel(new EarthGravityModel(), new AtmosphericDrag(0.015));
$integrator = new RungeKutta4($forceModel);

// Propagate 1/4 orbit (~25 minutes) to evaluate cross-link network topology
$states = [];
foreach ($satellites as $id => $elements) {
    $states[$id] = $elements->toStateVector();
}

$satKeys = array_keys($satellites);
$link = new InterSatelliteLink($satKeys[0], $satKeys[1], 4500.0);

printf(" %-15s <-> %-15s | %-12s | %-15s\n", 'Source Node', 'Target Node', 'Distance', 'Optical ISL');
echo str_repeat('-', 65) . "\n";

for ($i = 0; $i < count($satKeys); $i++) {
    for ($j = $i + 1; $j < count($satKeys); $j++) {
        $idA = $satKeys[$i];
        $idB = $satKeys[$j];

        $range = $link->computeRangeKm($states[$idA]->position, $states[$idB]->position);
        $hasLos = $link->hasLineOfSight($states[$idA]->position, $states[$idB]->position);

        if ($hasLos && $range <= 4500.0) {
            printf(" %-15s <-> %-15s | %8.1f km  | \033[32mESTABLISHED\033[0m\n", $idA, $idB, $range);
        }
    }
}
echo "\n";

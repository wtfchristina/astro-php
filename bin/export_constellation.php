<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Constellation\InterSatelliteLink;
use Astro\Constellation\WalkerDeltaConstellation;
use Astro\Force\AtmosphericDrag;
use Astro\Force\CompositeForceModel;
use Astro\Force\EarthGravityModel;
use Astro\Integrator\RungeKutta4;
use Astro\Serializer\CzmlWriter;

echo "========================================================================\n";
echo " AstroPHP: Walker-Delta 12/3/1 Constellation & ISL Mesh CZML Pipeline\n";
echo "========================================================================\n\n";

$walker = new WalkerDeltaConstellation(12, 3, 1, 700.0, 53.0);
$satellites = $walker->generateSatellites();

$forceModel = new CompositeForceModel(new EarthGravityModel(), new AtmosphericDrag(0.015));
$integrator = new RungeKutta4($forceModel);

$epoch = new DateTimeImmutable('2026-10-22T12:00:00Z');
$duration = 5900.0;
$stepSize = 20.0;

echo "Propagating 12 satellites over 1 complete revolution ({$duration}s, dt={$stepSize}s)...\n";
$startBenchmark = hrtime(true);

$trajectories = [];
foreach ($satellites as $satId => $elements) {
    $state = $elements->toStateVector();
    $t = 0.0;
    $traj = [];

    while ($t <= $duration) {
        $traj[] = [
            'epoch' => $epoch->modify('+' . (int)round($t) . ' seconds')->format('Y-m-d\TH:i:s\Z'),
            'state' => $state,
        ];
        $state = $integrator->step($state, $t, $stepSize);
        $t += $stepSize;
    }
    $trajectories[$satId] = $traj;
}

$elapsedMs = (hrtime(true) - $startBenchmark) / 1e6;
echo "Constellation propagation finished in " . number_format($elapsedMs, 2) . " ms.\n";

$satKeys = array_keys($satellites);
$islChecker = new InterSatelliteLink('', '', 4500.0);
$activeLinks = [];

for ($i = 0; $i < count($satKeys); $i++) {
    for ($j = $i + 1; $j < count($satKeys); $j++) {
        $idA = $satKeys[$i];
        $idB = $satKeys[$j];
        $posA = $trajectories[$idA][0]['state']->position;
        $posB = $trajectories[$idB][0]['state']->position;

        if ($islChecker->hasLineOfSight($posA, $posB)) {
            $activeLinks[] = ['satA' => $idA, 'satB' => $idB];
        }
    }
}

echo "Established " . count($activeLinks) . " optical ISL links across the constellation.\n";

$writer = new CzmlWriter();
$czml = $writer->exportConstellation($trajectories, $activeLinks);
file_put_contents(__DIR__ . '/../constellation.czml', $czml);

echo "Successfully generated: constellation.czml\n\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Constellation\DopplerAnalyzer;
use Astro\Constellation\InterSatelliteLink;
use Astro\Constellation\WalkerDeltaConstellation;
use Astro\Force\AtmosphericDrag;
use Astro\Force\CompositeForceModel;
use Astro\Force\EarthGravityModel;
use Astro\Integrator\RungeKutta4;

echo "========================================================================\n";
echo " AstroPHP: Walker-Delta Optical Inter-Satellite Doppler Profiler\n";
echo "========================================================================\n\n";

$walker = new WalkerDeltaConstellation(12, 3, 1, 700.0, 53.0);
$satellites = $walker->generateSatellites();

$forceModel = new CompositeForceModel(new EarthGravityModel(), new AtmosphericDrag(0.015));
$integrator = new RungeKutta4($forceModel);
$analyzer = new DopplerAnalyzer(1550.0);
$islChecker = new InterSatelliteLink('', '', 5000.0);

$states = [];
foreach ($satellites as $id => $elements) {
    $states[$id] = $elements->toStateVector();
}

$satKeys = array_keys($states);
$maxDopplerShiftGhz = 0.0;
$maxRangeRateKmS = 0.0;
$intraPlaneShifts = [];
$interPlaneShifts = [];

for ($i = 0; $i < count($satKeys); $i++) {
    for ($j = $i + 1; $j < count($satKeys); $j++) {
        $idA = $satKeys[$i];
        $idB = $satKeys[$j];
        $stateA = $states[$idA];
        $stateB = $states[$idB];

        if ($islChecker->hasLineOfSight($stateA->position, $stateB->position)) {
            $analysis = $analyzer->analyzeLink($stateA, $stateB);
            $shiftMag = abs($analysis['dopplerShiftGhz']);
            $rateMag = abs($analysis['rangeRateKmS']);

            if ($shiftMag > $maxDopplerShiftGhz) {
                $maxDopplerShiftGhz = $shiftMag;
                $maxRangeRateKmS = $rateMag;
            }

            preg_match('/P(\d+)/', $idA, $mA);
            preg_match('/P(\d+)/', $idB, $mB);
            $isSamePlane = ($mA[1] ?? '') === ($mB[1] ?? '');

            if ($isSamePlane) {
                $intraPlaneShifts[] = $analysis;
            } else {
                $interPlaneShifts[] = $analysis;
            }
        }
    }
}

printf("Carrier Optical Parameters (1550 nm C-Band Optical ISL):\n");
printf(" • Nominal Carrier Frequency: %.4f THz\n", (DopplerAnalyzer::SPEED_OF_LIGHT_KM_S * 1e3 / 1550e-9) / 1e12);
printf(" • Active Analyzed Links    : %d links\n\n", count($intraPlaneShifts) + count($interPlaneShifts));

printf("Intra-Plane Laser Cross-Links (Same Orbital Plane):\n");
if (!empty($intraPlaneShifts)) {
    $first = $intraPlaneShifts[0];
    printf(" • Range Rate           : %.4f km/s\n", $first['rangeRateKmS']);
    printf(" • Doppler Frequency Shift: %.4f MHz\n\n", $first['dopplerShiftHz'] / 1e6);
}

printf("Inter-Plane Laser Cross-Links (Cross-Plane Cross-Seam Routing):\n");
printf(" • Max Range Rate (|v_los|): %.4f km/s\n", $maxRangeRateKmS);
printf(" • Max Optical Doppler Drift : ±%.4f GHz\n", $maxDopplerShiftGhz);
printf(" • Recommended Receiver Tracking Loop Bandwidth: > %.2f GHz\n\n", $maxDopplerShiftGhz * 1.25);

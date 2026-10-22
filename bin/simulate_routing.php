<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Astro\Constellation\InterSatelliteLink;
use Astro\Constellation\NetworkRouter;
use Astro\Constellation\WalkerDeltaConstellation;
use Astro\Coordinate\Wgs84Transformer;
use Astro\GroundStation\GroundStation;

echo "====================================================================\n";
echo " AstroPHP: Dynamic Constellation Multi-Hop Optical ISL Router\n";
echo "====================================================================\n\n";

$walker = new WalkerDeltaConstellation(12, 3, 1, 700.0, 53.0);
$satellites = $walker->generateSatellites();

$gsPhoenix = new GroundStation('GS-PHX', 33.4484, -112.0740, 0.331, 10.0);
$gsSvalbard = new GroundStation('GS-SGS', 78.2297, 15.4077, 0.400, 5.0);

$transformer = new Wgs84Transformer();
$gmstRad = 0.0;

// Convert nodes to instantaneous ECI coordinates
$nodes = [];
$nodes['GS-PHX'] = $transformer->ecefToEci($gsPhoenix->toEcef(), $gmstRad);
$nodes['GS-SGS'] = $transformer->ecefToEci($gsSvalbard->toEcef(), $gmstRad);

foreach ($satellites as $id => $elements) {
    $nodes[$id] = $elements->toStateVector()->position;
}

$isl = new InterSatelliteLink('', '', 5000.0);
$edges = [];
$nodeKeys = array_keys($nodes);

// Evaluate all candidate laser and RF links
for ($i = 0; $i < count($nodeKeys); $i++) {
    for ($j = $i + 1; $j < count($nodeKeys); $j++) {
        $u = $nodeKeys[$i];
        $v = $nodeKeys[$j];
        $posA = $nodes[$u];
        $posB = $nodes[$v];

        $dist = $isl->computeRangeKm($posA, $posB);
        if ($dist <= 5000.0 && $isl->hasLineOfSight($posA, $posB)) {
            $edges[] = ['u' => $u, 'v' => $v, 'dist' => $dist];
        }
    }
}

echo "Topological Graph Summary:\n";
echo " • Total Network Vertices : " . count($nodes) . " (12 Satellites + 2 Ground Stations)\n";
echo " • Active Optical/RF Edges: " . count($edges) . "\n\n";

$router = new NetworkRouter();
$route = $router->findShortestPath($nodes, $edges, 'GS-PHX', 'GS-SGS');

if ($route !== null) {
    echo "Shortest Optical ISL Path Found:\n";
    echo " Route: " . implode("  --->  ", $route['path']) . "\n";
    echo " • Hops             : " . (count($route['path']) - 1) . "\n";
    echo " • Slant Path Length: " . number_format($route['totalDistanceKm'], 2) . " km\n";
    echo " • One-Way Latency  : " . number_format($route['latencyMs'], 3) . " ms (Speed of Light)\n\n";
} else {
    echo "No direct line-of-sight route available at this epoch.\n\n";
}

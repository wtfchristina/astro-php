<?php

declare(strict_types=1);

namespace Astro\GroundStation;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Wgs84Transformer;

final class PassScheduleService
{
    private const OMEGA_EARTH = 7.2921159e-5; // rad/s

    public function __construct(
        private readonly PassPredictor $predictor = new PassPredictor(),
        private readonly Wgs84Transformer $transformer = new Wgs84Transformer()
    ) {}

    /**
     * @param list<array{epoch: string, state: StateVector}> $trajectory
     * @return list<PassWindow>
     */
    public function findPasses(GroundStation $station, array $trajectory, float $initialGmstRad = 0.0): array
    {
        $passes = [];
        $inPass = false;

        $aosEpoch = null;
        $maxEl = 0.0;
        $minRange = INF;
        $tcaEpoch = null;

        $t0 = strtotime($trajectory[0]['epoch']);

        foreach ($trajectory as $point) {
            $epochStr = $point['epoch'];
            $t = (float)(strtotime($epochStr) - $t0);

            // Propagate GMST angle forward in time
            $gmst = fmod($initialGmstRad + self::OMEGA_EARTH * $t, 2.0 * M_PI);
            $satEcef = $this->transformer->eciToEcef($point['state']->position, $gmst);
            $look = $this->predictor->calculateLookAngles($station, $satEcef);

            if ($look->elevationDeg >= $station->minElevationDeg) {
                if (!$inPass) {
                    $inPass = true;
                    $aosEpoch = $epochStr;
                    $maxEl = $look->elevationDeg;
                    $minRange = $look->rangeKm;
                    $tcaEpoch = $epochStr;
                } else {
                    if ($look->elevationDeg > $maxEl) {
                        $maxEl = $look->elevationDeg;
                        $minRange = $look->rangeKm;
                        $tcaEpoch = $epochStr;
                    }
                }
            } else {
                if ($inPass && $aosEpoch !== null && $tcaEpoch !== null) {
                    $inPass = false;
                    $losEpoch = $epochStr;
                    $duration = (float)(strtotime($losEpoch) - strtotime($aosEpoch));

                    $passes[] = new PassWindow(
                        stationName: $station->name,
                        aosEpoch: $aosEpoch,
                        tcaEpoch: $tcaEpoch,
                        losEpoch: $losEpoch,
                        durationSeconds: $duration,
                        maxElevationDeg: $maxEl,
                        minRangeKm: $minRange
                    );

                    $aosEpoch = null;
                    $tcaEpoch = null;
                }
            }
        }

        return $passes;
    }
}

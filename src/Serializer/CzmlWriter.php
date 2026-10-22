<?php

declare(strict_types=1);

namespace Astro\Serializer;

use Astro\Coordinate\StateVector;
use Astro\Coordinate\Wgs84Transformer;
use Astro\GroundStation\GroundStation;
use Astro\GroundStation\PassWindow;

final class CzmlWriter
{
    private Wgs84Transformer $transformer;
    private const OMEGA_EARTH = 7.2921159e-5;

    public function __construct()
    {
        $this->transformer = new Wgs84Transformer();
    }

    /**
     * @param list<array{epoch: string, state: StateVector}> $trajectory
     * @param list<GroundStation> $stations
     * @param list<PassWindow> $passes
     */
    public function export(
        string $satelliteId,
        array $trajectory,
        array $stations = [],
        array $passes = [],
        float $initialGmstRad = 0.0
    ): string {
        $startEpoch = $trajectory[0]['epoch'];
        $endEpoch = end($trajectory)['epoch'];

        $czml = [
            [
                'id' => 'document',
                'name' => 'AstroPHP Ephemeris & Ground Track',
                'version' => '1.0',
            ],
            [
                'id' => $satelliteId,
                'name' => $satelliteId,
                'availability' => "{$startEpoch}/{$endEpoch}",
                'point' => [
                    'pixelSize' => 10,
                    'color' => ['rgba' => [255, 230, 0, 255]],
                    'outlineColor' => ['rgba' => [255, 255, 255, 255]],
                    'outlineWidth' => 2,
                ],
                'path' => [
                    'material' => [
                        'solidColor' => [
                            'color' => ['rgba' => [0, 220, 255, 255]]
                        ]
                    ],
                    'width' => 2,
                    'leadTime' => 86400,
                    'trailTime' => 86400,
                    'resolution' => 30,
                ],
                'position' => [
                    'referenceFrame' => 'INERTIAL',
                    'epoch' => $startEpoch,
                    'cartesian' => $this->flattenCartesian($trajectory, $startEpoch),
                ],
            ]
        ];

        // 1. Add Sub-Satellite Ground Track (Surface projection in WGS-84 fixed frame)
        $czml[] = [
            'id' => "{$satelliteId}-groundtrack",
            'name' => "{$satelliteId} Ground Track",
            'availability' => "{$startEpoch}/{$endEpoch}",
            'polyline' => [
                'positions' => [
                    'cartographicDegrees' => $this->buildGroundTrackCartographic($trajectory, $startEpoch, $initialGmstRad),
                ],
                'material' => [
                    'solidColor' => [
                        'color' => ['rgba' => [255, 140, 0, 180]]
                    ]
                ],
                'width' => 1.5,
                'clampToGround' => true,
            ]
        ];

        // 2. Add Ground Station entities
        foreach ($stations as $station) {
            $stationId = 'GS-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $station->name);
            $czml[] = [
                'id' => $stationId,
                'name' => $station->name,
                'position' => [
                    'cartographicDegrees' => [
                        $station->longitudeDeg,
                        $station->latitudeDeg,
                        $station->altitudeKm * 1000.0
                    ]
                ],
                'point' => [
                    'pixelSize' => 8,
                    'color' => ['rgba' => [50, 255, 50, 255]],
                    'outlineColor' => ['rgba' => [0, 0, 0, 255]],
                    'outlineWidth' => 2,
                ],
                'label' => [
                    'text' => $station->name,
                    'font' => '11pt monospace',
                    'style' => 'FILL_AND_OUTLINE',
                    'fillColor' => ['rgba' => [255, 255, 255, 255]],
                    'outlineColor' => ['rgba' => [0, 0, 0, 255]],
                    'outlineWidth' => 2,
                    'pixelOffset' => ['cartesian2' => [0, -14]],
                    'horizontalOrigin' => 'CENTER',
                ]
            ];
        }

        return json_encode($czml, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{epoch: string, state: StateVector}> $trajectory
     * @return list<float>
     */
    private function flattenCartesian(array $trajectory, string $startEpoch): array
    {
        $data = [];
        $t0 = strtotime($startEpoch);

        foreach ($trajectory as $point) {
            $offset = (float)(strtotime($point['epoch']) - $t0);
            $pos = $point['state']->position;

            $data[] = $offset;
            $data[] = round($pos->x, 3);
            $data[] = round($pos->y, 3);
            $data[] = round($pos->z, 3);
        }

        return $data;
    }

    /**
     * @param list<array{epoch: string, state: StateVector}> $trajectory
     * @return list<float>
     */
    private function buildGroundTrackCartographic(array $trajectory, string $startEpoch, float $initialGmstRad): array
    {
        $coords = [];
        $t0 = strtotime($startEpoch);

        foreach ($trajectory as $point) {
            $t = (float)(strtotime($point['epoch']) - $t0);
            $gmst = fmod($initialGmstRad + self::OMEGA_EARTH * $t, 2.0 * M_PI);
            $ecef = $this->transformer->eciToEcef($point['state']->position, $gmst);
            $geo = $this->transformer->ecefToGeodetic($ecef);

            $coords[] = round($geo->longitudeDeg, 4);
            $coords[] = round($geo->latitudeDeg, 4);
            $coords[] = 0.0; // Clamped to Earth surface
        }

        return $coords;
    }
}

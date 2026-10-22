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
                    'material' => ['solidColor' => ['color' => ['rgba' => [0, 220, 255, 255]]]],
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
            ],
            [
                'id' => "{$satelliteId}-groundtrack",
                'name' => "{$satelliteId} Ground Track",
                'availability' => "{$startEpoch}/{$endEpoch}",
                'polyline' => [
                    'positions' => [
                        'cartographicDegrees' => $this->buildGroundTrackCartographic($trajectory, $startEpoch, $initialGmstRad),
                    ],
                    'material' => ['solidColor' => ['color' => ['rgba' => [255, 140, 0, 180]]]],
                    'width' => 1.5,
                    'clampToGround' => true,
                ]
            ]
        ];

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

        $passIndex = 0;
        foreach ($passes as $p) {
            $stationId = 'GS-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $p->stationName);
            $czml[] = [
                'id' => "LINK-{$stationId}-{$passIndex}",
                'name' => "RF Link [{$p->stationName}]",
                'availability' => "{$p->aosEpoch}/{$p->losEpoch}",
                'polyline' => [
                    'positions' => ['references' => ["{$satelliteId}#position", "{$stationId}#position"]],
                    'material' => [
                        'polylineGlow' => [
                            'color' => ['rgba' => [0, 255, 120, 255]],
                            'glowPower' => 0.25,
                            'taperPower' => 0.5
                        ]
                    ],
                    'width' => 3
                ]
            ];
            $passIndex++;
        }

        return json_encode($czml, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, list<array{epoch: string, state: StateVector}>> $trajectories
     * @param list<array{satA: string, satB: string}> $staticLinks
     */
    public function exportConstellation(array $trajectories, array $staticLinks = []): string
    {
        $firstKey = array_key_first($trajectories);
        $startEpoch = $trajectories[$firstKey][0]['epoch'];
        $endEpoch = end($trajectories[$firstKey])['epoch'];

        $czml = [
            [
                'id' => 'document',
                'name' => 'AstroPHP Walker-Delta Constellation & ISL Mesh',
                'version' => '1.0',
            ]
        ];

        $planeColors = [
            ['rgba' => [0, 229, 255, 255]],
            ['rgba' => [255, 170, 0, 255]],
            ['rgba' => [180, 0, 255, 255]],
            ['rgba' => [0, 255, 128, 255]],
        ];

        foreach ($trajectories as $satId => $traj) {
            preg_match('/P(\d+)/', $satId, $matches);
            $planeIdx = isset($matches[1]) ? (((int)$matches[1] - 1) % count($planeColors)) : 0;
            $color = $planeColors[$planeIdx];

            $czml[] = [
                'id' => $satId,
                'name' => $satId,
                'availability' => "{$startEpoch}/{$endEpoch}",
                'point' => [
                    'pixelSize' => 8,
                    'color' => $color,
                    'outlineColor' => ['rgba' => [255, 255, 255, 255]],
                    'outlineWidth' => 1,
                ],
                'label' => [
                    'text' => $satId,
                    'font' => '10pt monospace',
                    'fillColor' => $color,
                    'outlineColor' => ['rgba' => [0, 0, 0, 255]],
                    'outlineWidth' => 2,
                    'pixelOffset' => ['cartesian2' => [0, -12]],
                    'horizontalOrigin' => 'CENTER',
                ],
                'path' => [
                    'material' => ['solidColor' => ['color' => $color]],
                    'width' => 1.2,
                    'leadTime' => 5400,
                    'trailTime' => 5400,
                    'resolution' => 60,
                ],
                'position' => [
                    'referenceFrame' => 'INERTIAL',
                    'epoch' => $startEpoch,
                    'cartesian' => $this->flattenCartesian($traj, $startEpoch),
                ],
            ];
        }

        $linkIndex = 0;
        foreach ($staticLinks as $link) {
            $satA = $link['satA'];
            $satB = $link['satB'];
            $czml[] = [
                'id' => "ISL-{$satA}-{$satB}-{$linkIndex}",
                'name' => "Optical ISL [{$satA} <-> {$satB}]",
                'availability' => "{$startEpoch}/{$endEpoch}",
                'polyline' => [
                    'positions' => ['references' => ["{$satA}#position", "{$satB}#position"]],
                    'material' => [
                        'polylineGlow' => [
                            'color' => ['rgba' => [0, 255, 180, 220]],
                            'glowPower' => 0.2,
                            'taperPower' => 0.8
                        ]
                    ],
                    'width' => 1.8
                ]
            ];
            $linkIndex++;
        }

        return json_encode($czml, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

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
            $coords[] = 0.0;
        }

        return $coords;
    }
}

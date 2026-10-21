<?php

declare(strict_types=1);

namespace Astro\Serializer;

use Astro\Coordinate\StateVector;

final class CzmlWriter
{
    /**
     * @param list<array{epoch: string, state: StateVector}> $trajectory
     */
    public function export(string $satelliteId, array $trajectory): string
    {
        $startEpoch = $trajectory[0]['epoch'];
        $endEpoch = end($trajectory)['epoch'];

        $czml = [
            [
                'id' => 'document',
                'name' => 'AstroPHP Ephemeris',
                'version' => '1.0',
            ],
            [
                'id' => $satelliteId,
                'name' => $satelliteId,
                'availability' => "{$startEpoch}/{$endEpoch}",
                'path' => [
                    'material' => [
                        'solidColor' => [
                            'color' => ['rgba' => [0, 220, 255, 255]]
                        ]
                    ],
                    'width' => 2,
                    'leadTime' => 7200,
                    'trailTime' => 7200,
                    'resolution' => 60,
                ],
                'position' => [
                    'referenceFrame' => 'INERTIAL',
                    'epoch' => $startEpoch,
                    'cartesian' => $this->flattenCartesian($trajectory, $startEpoch),
                ],
            ]
        ];

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
}

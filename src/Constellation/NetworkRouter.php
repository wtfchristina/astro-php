<?php

declare(strict_types=1);

namespace Astro\Constellation;

use Astro\Coordinate\Vector3;
use SplPriorityQueue;

final class NetworkRouter
{
    private const C_KM_S = 299792.458; // Speed of light in km/s

    /**
     * @param array<string, Vector3> $nodePositions ECI positions of satellites and ground stations
     * @param list<array{u: string, v: string, dist: float}> $edges Valid unobstructed links
     * @return array{path: list<string>, latencyMs: float, totalDistanceKm: float}|null
     */
    public function findShortestPath(array $nodePositions, array $edges, string $source, string $target): ?array
    {
        $adj = [];
        foreach (array_keys($nodePositions) as $node) {
            $adj[$node] = [];
        }

        foreach ($edges as $edge) {
            $u = $edge['u'];
            $v = $edge['v'];
            $dist = $edge['dist'];
            $adj[$u][] = ['node' => $v, 'weight' => $dist];
            $adj[$v][] = ['node' => $u, 'weight' => $dist];
        }

        $distances = [];
        $previous = [];
        /** @var SplPriorityQueue<int, array{0: string, 1: float}> $pq */
        $pq = new class extends SplPriorityQueue {
            public function compare(mixed $priority1, mixed $priority2): int {
                return $priority2 <=> $priority1; // Min-heap
            }
        };

        foreach (array_keys($nodePositions) as $node) {
            $distances[$node] = INF;
            $previous[$node] = null;
        }

        $distances[$source] = 0.0;
        $pq->insert([$source, 0.0], 0);

        while (!$pq->isEmpty()) {
            [$u, $d] = $pq->extract();

            if ($u === $target) {
                break;
            }

            if ($d > $distances[$u]) {
                continue;
            }

            foreach ($adj[$u] as $neighbor) {
                $v = $neighbor['node'];
                $weight = $neighbor['weight'];
                $alt = $distances[$u] + $weight;

                if ($alt < $distances[$v]) {
                    $distances[$v] = $alt;
                    $previous[$v] = $u;
                    $pq->insert([$v, $alt], (int)round($alt * 1000));
                }
            }
        }

        if ($distances[$target] === INF) {
            return null;
        }

        $path = [];
        $curr = $target;
        while ($curr !== null) {
            array_unshift($path, $curr);
            $curr = $previous[$curr];
        }

        $totalDistanceKm = $distances[$target];
        $latencyMs = ($totalDistanceKm / self::C_KM_S) * 1000.0;

        return [
            'path' => $path,
            'latencyMs' => $latencyMs,
            'totalDistanceKm' => $totalDistanceKm,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Astro\Tests\Constellation;

use Astro\Constellation\NetworkRouter;
use Astro\Coordinate\Vector3;
use PHPUnit\Framework\TestCase;

final class NetworkRouterTest extends TestCase
{
    public function testMultiHopShortestPathRouting(): void
    {
        $router = new NetworkRouter();

        $nodes = [
            'GS-PHX' => new Vector3(0.0, 0.0, 0.0),
            'SAT-01' => new Vector3(0.0, 1000.0, 0.0),
            'SAT-02' => new Vector3(0.0, 3000.0, 0.0),
            'GS-SGS' => new Vector3(0.0, 4000.0, 0.0),
        ];

        $edges = [
            ['u' => 'GS-PHX', 'v' => 'SAT-01', 'dist' => 1000.0],
            ['u' => 'SAT-01', 'v' => 'SAT-02', 'dist' => 2000.0],
            ['u' => 'SAT-02', 'v' => 'GS-SGS', 'dist' => 1000.0],
        ];

        $route = $router->findShortestPath($nodes, $edges, 'GS-PHX', 'GS-SGS');

        $this->assertNotNull($route);
        $this->assertSame(['GS-PHX', 'SAT-01', 'SAT-02', 'GS-SGS'], $route['path']);
        $this->assertEqualsWithDelta(4000.0, $route['totalDistanceKm'], 1e-4);
        $this->assertEqualsWithDelta(13.3425, $route['latencyMs'], 0.01);
    }

    public function testUnreachableTargetReturnsNull(): void
    {
        $router = new NetworkRouter();
        $nodes = [
            'GS-A' => new Vector3(0.0, 0.0, 0.0),
            'GS-B' => new Vector3(10000.0, 0.0, 0.0),
        ];
        $edges = [];

        $route = $router->findShortestPath($nodes, $edges, 'GS-A', 'GS-B');
        $this->assertNull($route);
    }
}

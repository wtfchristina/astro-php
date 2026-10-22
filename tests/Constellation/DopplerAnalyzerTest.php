<?php

declare(strict_types=1);

namespace Astro\Tests\Constellation;

use Astro\Constellation\DopplerAnalyzer;
use Astro\Coordinate\StateVector;
use Astro\Coordinate\Vector3;
use PHPUnit\Framework\TestCase;

final class DopplerAnalyzerTest extends TestCase
{
    public function testZeroRelativeVelocityYieldsZeroDoppler(): void
    {
        $analyzer = new DopplerAnalyzer(1550.0);

        $satA = new StateVector(new Vector3(7000.0, 0.0, 0.0), new Vector3(0.0, 7.5, 0.0));
        $satB = new StateVector(new Vector3(8000.0, 0.0, 0.0), new Vector3(0.0, 7.5, 0.0));

        $result = $analyzer->analyzeLink($satA, $satB);

        $this->assertEqualsWithDelta(1000.0, $result['rangeKm'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $result['rangeRateKmS'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $result['dopplerShiftHz'], 1e-3);
        $this->assertEqualsWithDelta($result['carrierFrequencyHz'], $result['receivedFrequencyHz'], 1e-3);
    }

    public function testHeadOnApproachYieldsBlueShift(): void
    {
        $analyzer = new DopplerAnalyzer(1550.0);

        // Transmitter at (1000, 0, 0) moving towards origin at 5 km/s
        $tx = new StateVector(new Vector3(1000.0, 0.0, 0.0), new Vector3(-5.0, 0.0, 0.0));
        // Receiver stationary at origin
        $rx = new StateVector(new Vector3(0.0, 0.0, 0.0), new Vector3(0.0, 0.0, 0.0));

        $result = $analyzer->analyzeLink($tx, $rx);

        // Range rate is -5 km/s (closing distance)
        $this->assertEqualsWithDelta(-5.0, $result['rangeRateKmS'], 1e-6);
        // Approaching yields positive frequency shift (blue-shift): delta_f = -(-5000 m/s) / (1550e-9 m) = +3.2258 GHz
        $expectedShiftGhz = (5000.0 / 1.55e-6) / 1e9;
        $this->assertEqualsWithDelta($expectedShiftGhz, $result['dopplerShiftGhz'], 1e-4);
        $this->assertGreaterThan($result['carrierFrequencyHz'], $result['receivedFrequencyHz']);
    }

    public function testRecedingYieldsRedShift(): void
    {
        $analyzer = new DopplerAnalyzer(1064.0); // Nd:YAG 1064 nm laser

        // Transmitter at origin moving away from receiver at 3 km/s
        $tx = new StateVector(new Vector3(0.0, 0.0, 0.0), new Vector3(0.0, 0.0, 0.0));
        $rx = new StateVector(new Vector3(2000.0, 0.0, 0.0), new Vector3(3.0, 0.0, 0.0));

        $result = $analyzer->analyzeLink($tx, $rx);

        $this->assertEqualsWithDelta(3.0, $result['rangeRateKmS'], 1e-6);
        // Receding yields negative frequency shift (red-shift)
        $this->assertLessThan(0.0, $result['dopplerShiftHz']);
        $this->assertLessThan($result['carrierFrequencyHz'], $result['receivedFrequencyHz']);
    }
}

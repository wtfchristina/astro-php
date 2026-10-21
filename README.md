# AstroPHP

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-Passing-success)](tests/)

AstroPHP is a numerical orbital mechanics engine and mission trajectory propagator written in strictly-typed PHP 8.3+.

It executes high-fidelity Cowell-method numerical orbital propagation, geopotential perturbations ($J_2$), and standard ephemeris exports with microsecond step-times.

---

## Physics Models & Features

* **Two-Body Gravity:** WGS84 standard central force acceleration ($\mu = 3.986004418 \times 10m{14} \text{ m}^3\/text{s}^2$).
* **Geopotential Oblateness ($J_2$):** Earth oblateness driving secular precession of the Right Ascension of the Ascending Node ($\Omega$) and Argument of Perigee ($\omega$).
* **Kepler Solver:** Newton-Raphson iteration solving Kepler's Equation to convert orbital elements into Cartesian state vectors in the ECI frame.
* **Numerical Integration:** Explicit 4th-Order Runge-Kuta (RK4) vector solver.
* **Visualization:** Exports standard Cesium CZML ephemeris streams for 3D browser rendering.

---

## CLI Simulation

Propagate a 700 km Sun-Synchronous Orbit across 3 complete revolutions:

```bash
php bin/propagate.php
```

This generates `orbit.czml` in the repository root. Drop this file into [Cesium Sandcastle](https://sandcastle.cesium.com/) to render the 3D trajectory.

---

## Automated Verification

Verify the numerical integration engine against analytical $J_2$ nodal precession theory ($0.9856^\circ/\text{day}$):

```bash
vendor/bin/phpunit
```

---

## License

MIT License.

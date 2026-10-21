<?php

declare(strict_types=1);

namespace Astro\Coordinate;

final readonly class Vector3
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
    ) {}

    public function add(self $other): self
    {
        return new self($this->x +$other->x, $this->y +$other->y, $this->z +$other->z);
    }

    public function subtract(self $other): self
    {
        return new self($this->x -$other->x, $this->y -$other->y, $this->z -$other->z);
    }

    public function scale(float $scalar): self
    {
        return new self($this->x * $scalar, $this->y * $scalar, $this->z * $scalar);
    }

    public function dot(self $other): float
    {
        return ($this->x * $other->x) + ($this->y * $other->y) + ($this->z * $other->z);
    }

    public function cross(self $other): self
    {
        return new self(
            ($this->y * $other->z) - ($this->z * $other->y),
            ($this->z * $other->x) - ($this->x * $other->z),
            ($this->x * $other->y) - ($this->y * $other->x)
        );
    }

    public function magnitudeSquared(): float
    {
        return ($this->x ** 2) + ($this->y ** 2) + ($this->z ** 2);
    }

    public function magnitude(): float
    {
        return sqrt($this->magnitudeSquared());
    }

    public function normalize(): self
    {
        $mag =$this->magnitude();
        if ($mag === 0.0) {
            return new self(0.0, 0.0, 0.0);
        }
        return $this->scale(1.0 / $mag);
    }
}

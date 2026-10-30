<?php

declare(strict_types=1);

namespace App;

final class Bbox
{
    private const MAX_AREA_DEG2 = 0.000101;
    private const MAX_SIZE_DEG = 0.03;

    private function __construct(
        public readonly float $minLon,
        public readonly float $minLat,
        public readonly float $maxLon,
        public readonly float $maxLat,
    ) {}

    public static function fromString(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $parts = explode(",", $raw);

        if (count($parts) !== 4) {
            return null;
        }

        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                return null;
            }
        }

        [$minLon, $minLat, $maxLon, $maxLat] = array_map("floatval", $parts);

        if ($minLon >= $maxLon || $minLat >= $maxLat) {
            return null;
        }

        $width = $maxLon - $minLon;
        $height = $maxLat - $minLat;

        if (
            $width > self::MAX_SIZE_DEG ||
            $height > self::MAX_SIZE_DEG ||
            $width * $height > self::MAX_AREA_DEG2
        ) {
            return null;
        }

        return new self($minLon, $minLat, $maxLon, $maxLat);
    }

    public function toCuzkParam(): string
    {
        return implode(",", [
            $this->minLat,
            $this->minLon,
            $this->maxLat,
            $this->maxLon,
        ]);
    }

    public function snapped(float $step): self
    {
        return new self(
            round(floor($this->minLon / $step) * $step, 6),
            round(floor($this->minLat / $step) * $step, 6),
            round(ceil($this->maxLon / $step) * $step, 6),
            round(ceil($this->maxLat / $step) * $step, 6),
        );
    }
}

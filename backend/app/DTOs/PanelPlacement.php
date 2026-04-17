<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * A single placed panel in segment-local 2D coordinates (meters, segment SW corner).
 */
final class PanelPlacement
{
    public function __construct(
        public readonly int    $index,
        public readonly int    $segmentIndex,
        public readonly float  $x,
        public readonly float  $y,
        public readonly float  $width,
        public readonly float  $height,
        public readonly string $orientation,   // portrait | landscape
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'segment' => $this->segmentIndex,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->width,
            'h' => $this->height,
            'orientation' => $this->orientation,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface GeminiImageServiceInterface
{
    /**
     * Turn a deterministic overlay PNG into a photorealistic rooftop render.
     * Implementations MUST NOT move, add or remove panels — they should only
     * enhance realism (lighting, reflections, tile texture).
     *
     * @return array{bytes: string, notes: string}
     */
    public function enhance(string $overlayPng, string $prompt): array;
}

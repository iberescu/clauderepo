<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Contracts\GeminiImageServiceInterface;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Services\Contracts\StaticMapsServiceInterface;

/**
 * Aggregates everything the "View logs" button needs for a single project:
 *
 *   - which implementation (fake vs real) is bound to every provider interface,
 *   - parsed rows from logs/api_calls.txt with classification (fake/live),
 *   - tailed progress + error lines,
 *   - a per-image manifest saying whether each artefact was produced by a
 *     deterministic fake or a live Google/Gemini response.
 *
 * Everything is derived from the append-only text logs and the Laravel
 * container bindings — no DB lookups.
 */
final class LogsService
{
    /** Tracked artefact paths and the provider that produces them. */
    private const IMAGE_SOURCES = [
        'solar/images/rgb.png'              => 'solar_api',
        'solar/images/mask.png'             => 'solar_api',
        'solar/images/flux.png'             => 'solar_api',
        'render/roof_base.png'              => 'deterministic',
        'render/roof_overlay.png'           => 'deterministic',
        'render/roof_render.png'            => 'gemini',
        'render/roof_render_3d.png'         => 'gemini',
        'render/real_aerial_overlay.png'    => 'solar_api+deterministic',
        'render/real_aerial_render_3d.png'  => 'gemini',
        'render/static_map_base.png'        => 'static_maps',
        'render/static_map_overlay.png'     => 'static_maps+deterministic',
        'render/static_map_render_3d.png'   => 'gemini',
    ];

    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly GeoSearchServiceInterface $geoSearch,
        private readonly CandidateDiscoveryServiceInterface $candidateDiscovery,
        private readonly SolarApiServiceInterface $solarApi,
        private readonly GeminiImageServiceInterface $gemini,
        private readonly StaticMapsServiceInterface $staticMaps,
    ) {
    }

    /** @return array<string, mixed> */
    public function forProject(string $projectId): array
    {
        $providers = $this->providers();
        return [
            'project_id' => $projectId,
            'providers'  => $providers,
            'images'     => $this->images($projectId, $providers['services']),
            'api_calls'  => $this->apiCalls($projectId),
            'progress'   => $this->status->progressLines($projectId, 300),
            'errors'     => $this->status->errorLines($projectId, 100),
        ];
    }

    /** @return array<string, mixed> */
    private function providers(): array
    {
        $fakeFlag = (bool) config('solar.fake_providers', true);

        $services = [
            'geo_search'          => $this->classify($this->geoSearch),
            'candidate_discovery' => $this->classify($this->candidateDiscovery),
            'solar_api'           => $this->classify($this->solarApi),
            'gemini'              => $this->classify($this->gemini),
            'static_maps'         => $this->classify($this->staticMaps),
        ];

        return [
            'fake_providers_flag' => $fakeFlag,
            'services'            => $services,
            'keys_present'        => [
                'google_maps'  => (string) config('solar.google.maps_api_key') !== '',
                'google_solar' => (string) config('solar.google.solar_api_key') !== '',
                'gemini'       => (string) config('solar.gemini.api_key') !== '',
            ],
        ];
    }

    /** @return array{kind: string, class: string} */
    private function classify(object $impl): array
    {
        $class = get_class($impl);
        $short = substr($class, (int) (strrpos($class, '\\') ?: -1) + 1);
        $kind  = str_contains($class, '\\Fake\\') ? 'fake' : (str_contains($class, '\\Google\\') ? 'live' : 'unknown');
        return ['kind' => $kind, 'class' => $short];
    }

    /**
     * @param array<string, array{kind: string, class: string}> $services
     * @return list<array<string, mixed>>
     */
    private function images(string $projectId, array $services): array
    {
        $out = [];
        foreach (self::IMAGE_SOURCES as $relPath => $producer) {
            $bytes = $this->projects->readBinary($projectId, $relPath);
            if ($bytes === null) {
                continue;
            }
            $out[] = [
                'path'     => $relPath,
                'producer' => $producer,
                'kind'     => $this->imageKind($producer, $services),
                'bytes'    => strlen($bytes),
            ];
        }
        return $out;
    }

    /** @param array<string, array{kind: string, class: string}> $services */
    private function imageKind(string $producer, array $services): string
    {
        $tokens = explode('+', $producer);
        $kinds = [];
        foreach ($tokens as $tok) {
            $kinds[] = match ($tok) {
                'deterministic' => 'deterministic',
                'solar_api'     => $services['solar_api']['kind'] ?? 'unknown',
                'gemini'        => $services['gemini']['kind'] ?? 'unknown',
                'static_maps'   => $services['static_maps']['kind'] ?? 'unknown',
                default         => 'unknown',
            };
        }
        if (in_array('live', $kinds, true) && !in_array('fake', $kinds, true)) {
            return 'live';
        }
        if (in_array('fake', $kinds, true) && !in_array('live', $kinds, true)) {
            return 'fake';
        }
        if ($kinds === ['deterministic']) {
            return 'deterministic';
        }
        return 'mixed';
    }

    /** @return list<array<string, mixed>> */
    private function apiCalls(string $projectId): array
    {
        $raw = $this->projects->readText($projectId, 'logs/api_calls.txt') ?? '';
        $lines = array_values(array_filter(preg_split('/\r?\n/', $raw) ?: []));

        $rows = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\[(?P<ts>[^\]]+)\]\s+(?P<label>\S+)\s+status=(?P<status>\d+)\s+duration_ms=(?P<dur>[\d.]+)(?:\s+note=(?P<note>.*))?$/', $line, $m)) {
                continue;
            }
            $label = $m['label'];
            $rows[] = [
                'timestamp'   => $m['ts'],
                'label'       => $label,
                'status'      => (int) $m['status'],
                'duration_ms' => (float) $m['dur'],
                'note'        => $m['note'] ?? '',
                'kind'        => str_starts_with($label, 'fake.') ? 'fake' : 'live',
            ];
        }
        return $rows;
    }
}

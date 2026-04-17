<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Candidate;
use App\DTOs\NormalizedQuery;
use App\DTOs\ProjectInput;
use App\Support\ProjectPaths;
use App\Support\TextKv;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * File-backed project storage. Every project lives under
 *   storage/app/projects/{project_id}/
 * and every step writes human-readable .txt files plus a `.json` sidecar for
 * machine-readable round-tripping.
 */
final class ProjectFileRepository
{
    public function __construct(private readonly ?Filesystem $disk = null)
    {
    }

    private function disk(): Filesystem
    {
        return $this->disk ?? Storage::disk(config('filesystems.default', 'local'));
    }

    public function paths(string $projectId): ProjectPaths
    {
        return new ProjectPaths($projectId);
    }

    public function exists(string $projectId): bool
    {
        return $this->disk()->exists((new ProjectPaths($projectId))->root());
    }

    public function create(string $projectId): ProjectPaths
    {
        $paths = new ProjectPaths($projectId);
        foreach ($paths->allDirs() as $dir) {
            $this->disk()->makeDirectory($dir);
        }
        return $paths;
    }

    /** @return list<string> */
    public function listProjectIds(): array
    {
        $root = config('solar.paths.projects', 'projects');
        $dirs = $this->disk()->directories($root);
        $ids  = array_map(fn ($d) => basename($d), $dirs);
        rsort($ids);
        return array_values($ids);
    }

    public function writeText(string $projectId, string $relative, string $contents): void
    {
        $full = (new ProjectPaths($projectId))->root().'/'.$relative;
        $this->disk()->put($full, $contents);
    }

    public function readText(string $projectId, string $relative): ?string
    {
        $full = (new ProjectPaths($projectId))->root().'/'.$relative;
        return $this->disk()->exists($full) ? (string) $this->disk()->get($full) : null;
    }

    public function writeJson(string $projectId, string $relative, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON for '.$relative);
        }
        $this->writeText($projectId, $relative, $json);
    }

    /** @return array<string, mixed>|null */
    public function readJson(string $projectId, string $relative): ?array
    {
        $raw = $this->readText($projectId, $relative);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    // --- Phase 1 helpers -----------------------------------------------------

    public function saveRequest(string $projectId, ProjectInput $in, \DateTimeImmutable $createdAt): void
    {
        $this->writeText($projectId, 'input/request.txt', TextKv::render([
            'project_id' => $projectId,
            'created_at' => $createdAt->format(DATE_ATOM),
            'street'     => $in->street,
            'city'       => $in->city,
            'country'    => $in->country,
        ]));
        $this->writeJson($projectId, 'input/request.json', [
            'project_id' => $projectId,
            'created_at' => $createdAt->format(DATE_ATOM),
            ...$in->toArray(),
        ]);
    }

    public function saveNormalizedQuery(string $projectId, NormalizedQuery $q): void
    {
        $this->writeText($projectId, 'input/normalized_query.txt', TextKv::render($q->toArray()));
        $this->writeJson($projectId, 'input/normalized_query.json', $q->toArray());
    }

    public function readNormalizedQuery(string $projectId): ?NormalizedQuery
    {
        $data = $this->readJson($projectId, 'input/normalized_query.json');
        if ($data === null) {
            return null;
        }
        return new NormalizedQuery(
            street: (string)($data['street'] ?? ''),
            city: (string)($data['city'] ?? ''),
            country: (string)($data['country'] ?? ''),
            formattedAddress: (string)($data['formatted_address'] ?? ''),
            centerLat: (float)($data['center_lat'] ?? 0),
            centerLng: (float)($data['center_lng'] ?? 0),
            confidence: (float)($data['confidence'] ?? 0),
            placeId: (string)($data['place_id'] ?? ''),
            provider: (string)($data['provider'] ?? 'fake'),
        );
    }

    /** @param list<Candidate> $candidates */
    public function saveCandidates(string $projectId, array $candidates): void
    {
        $lines = ['# candidates: '.count($candidates).' entries', ''];
        foreach ($candidates as $c) {
            $lines[] = sprintf(
                '%02d. %s | lat=%.6f lng=%.6f conf=%.2f solar=%s%s',
                $c->index,
                $c->formattedAddress,
                $c->lat,
                $c->lng,
                $c->confidence,
                $c->solarSupported ? 'yes' : 'no',
                $c->notes !== '' ? ' | '.$c->notes : '',
            );
        }
        $this->writeText($projectId, 'candidates/candidates.txt', implode("\n", $lines)."\n");
        $this->writeJson($projectId, 'candidates/candidates.json', array_map(fn ($c) => $c->toArray(), $candidates));

        foreach ($candidates as $c) {
            $file = sprintf('candidates/candidate_%03d.txt', $c->index);
            $this->writeText($projectId, $file, TextKv::render($c->toArray()));
        }
    }

    /** @return list<Candidate> */
    public function readCandidates(string $projectId): array
    {
        $data = $this->readJson($projectId, 'candidates/candidates.json') ?? [];
        $out = [];
        foreach ($data as $row) {
            $out[] = new Candidate(
                id: (string)($row['id'] ?? ''),
                index: (int)($row['index'] ?? 0),
                formattedAddress: (string)($row['formatted_address'] ?? ''),
                lat: (float)($row['lat'] ?? 0),
                lng: (float)($row['lng'] ?? 0),
                confidence: (float)($row['confidence'] ?? 0),
                solarSupported: (bool)($row['solar_supported'] ?? false),
                notes: (string)($row['notes'] ?? ''),
            );
        }
        return $out;
    }

    public function saveSelectedCandidate(string $projectId, Candidate $c): void
    {
        $this->writeText($projectId, 'building/selected_building.txt', TextKv::render([
            'selected_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            ...$c->toArray(),
        ]));
        $this->writeJson($projectId, 'building/selected_building.json', $c->toArray());
    }

    public function readSelectedCandidate(string $projectId): ?Candidate
    {
        $data = $this->readJson($projectId, 'building/selected_building.json');
        if ($data === null) {
            return null;
        }
        return new Candidate(
            id: (string)($data['id'] ?? ''),
            index: (int)($data['index'] ?? 0),
            formattedAddress: (string)($data['formatted_address'] ?? ''),
            lat: (float)($data['lat'] ?? 0),
            lng: (float)($data['lng'] ?? 0),
            confidence: (float)($data['confidence'] ?? 0),
            solarSupported: (bool)($data['solar_supported'] ?? false),
            notes: (string)($data['notes'] ?? ''),
        );
    }
}

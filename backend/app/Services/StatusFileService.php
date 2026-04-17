<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectFileRepository;

/**
 * Append-only status, progress, and error tracking implemented as plain text
 * files under status/ and logs/ — no database, no cache, no queue.
 */
final class StatusFileService
{
    public const STATUS_CREATED   = 'created';
    public const STATUS_SEARCHING = 'searching';
    public const STATUS_CANDIDATES_READY = 'candidates_ready';
    public const STATUS_CANDIDATE_SELECTED = 'candidate_selected';
    public const STATUS_ANALYZING = 'analyzing';
    public const STATUS_ANALYSIS_READY = 'analysis_ready';
    public const STATUS_LAYOUT_READY = 'layout_ready';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_RENDER_READY = 'render_ready';
    public const STATUS_PRICING_READY = 'pricing_ready';
    public const STATUS_PROPOSAL_READY = 'proposal_ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public function __construct(private readonly ProjectFileRepository $projects)
    {
    }

    public function setStatus(string $projectId, string $status, ?string $note = null): void
    {
        $payload = [
            'status' => $status,
            'updated_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        if ($note !== null) {
            $payload['note'] = $note;
        }
        $this->projects->writeText($projectId, 'status/current_status.txt',
            'status: '.$status."\n".
            'updated_at: '.$payload['updated_at'].
            ($note !== null ? "\nnote: ".$note : '').
            "\n"
        );
        $this->projects->writeJson($projectId, 'status/current_status.json', $payload);
        $this->progress($projectId, "status={$status}".($note ? " ({$note})" : ''));
    }

    public function progress(string $projectId, string $message): void
    {
        $line = sprintf('[%s] %s', (new \DateTimeImmutable('now'))->format(DATE_ATOM), $message);
        $this->append($projectId, 'status/progress.txt', $line);
        $this->append($projectId, 'logs/app_log.txt', 'PROGRESS '.$line);
    }

    public function error(string $projectId, string $message, ?\Throwable $e = null): void
    {
        $detail = $message;
        if ($e !== null) {
            $detail .= ' :: '.get_class($e).': '.$e->getMessage();
        }
        $line = sprintf('[%s] %s', (new \DateTimeImmutable('now'))->format(DATE_ATOM), $detail);
        $this->append($projectId, 'logs/errors.txt', $line);
        $this->append($projectId, 'logs/app_log.txt', 'ERROR '.$line);
        $this->append($projectId, 'status/progress.txt', $line);
    }

    public function apiCall(string $projectId, string $label, int $status, float $durationMs, string $note = ''): void
    {
        $line = sprintf(
            '[%s] %s status=%d duration_ms=%.1f%s',
            (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            $label,
            $status,
            $durationMs,
            $note !== '' ? ' note='.$note : ''
        );
        $this->append($projectId, 'logs/api_calls.txt', $line);
    }

    /** @return array{status: string, updated_at: string, note?: string}|null */
    public function currentStatus(string $projectId): ?array
    {
        /** @var array{status: string, updated_at: string, note?: string}|null $data */
        $data = $this->projects->readJson($projectId, 'status/current_status.json');
        return $data;
    }

    /** @return list<string> */
    public function progressLines(string $projectId, int $tail = 200): array
    {
        $raw = $this->projects->readText($projectId, 'status/progress.txt') ?? '';
        $lines = array_values(array_filter(preg_split('/\r?\n/', $raw) ?: []));
        return array_slice($lines, -1 * $tail);
    }

    /** @return list<string> */
    public function errorLines(string $projectId, int $tail = 100): array
    {
        $raw = $this->projects->readText($projectId, 'logs/errors.txt') ?? '';
        $lines = array_values(array_filter(preg_split('/\r?\n/', $raw) ?: []));
        return array_slice($lines, -1 * $tail);
    }

    private function append(string $projectId, string $relative, string $line): void
    {
        $existing = $this->projects->readText($projectId, $relative) ?? '';
        $this->projects->writeText($projectId, $relative, $existing.$line."\n");
    }
}

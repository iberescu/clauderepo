<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Relative paths (inside the configured filesystem disk) for a single project.
 * All paths are returned as forward-slash strings suitable for Laravel's
 * Storage facade. No filesystem operations happen here.
 */
final class ProjectPaths
{
    public function __construct(public readonly string $projectId)
    {
    }

    public function root(): string
    {
        return config('solar.paths.projects', 'projects').'/'.$this->projectId;
    }

    public function dir(string $name): string
    {
        return $this->root().'/'.$name;
    }

    public function file(string $dir, string $file): string
    {
        return $this->dir($dir).'/'.$file;
    }

    /** @return array<string, string> */
    public function allDirs(): array
    {
        $names = ['input','candidates','building','solar','layout','pricing','render','proposal','logs','status'];
        $out = [];
        foreach ($names as $n) {
            $out[$n] = $this->dir($n);
        }
        return $out;
    }
}

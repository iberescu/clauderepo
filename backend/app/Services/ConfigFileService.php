<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TextKv;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Editable defaults stored as flat text + JSON sidecars under
 *   storage/app/config/
 *
 * Each section (panel, pricing, savings, branding) seeds from config('solar.defaults.*')
 * on first read so the app boots with sane assumptions.
 */
final class ConfigFileService
{
    private const SECTIONS = ['panel', 'pricing', 'savings', 'branding'];

    public function __construct(private readonly ?Filesystem $disk = null)
    {
    }

    private function disk(): Filesystem
    {
        return $this->disk ?? Storage::disk(config('filesystems.default', 'local'));
    }

    private function root(): string
    {
        return config('solar.paths.config', 'config');
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $out = [];
        foreach (self::SECTIONS as $s) {
            $out[$s] = $this->section($s);
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public function section(string $name): array
    {
        $this->assertKnown($name);
        $file = $this->root().'/'.$name.'_defaults.json';
        if (!$this->disk()->exists($file)) {
            $this->seed($name);
        }
        $raw = (string) $this->disk()->get($file);
        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $this->defaults($name);
    }

    /** @param array<string, mixed> $values */
    public function updateSection(string $name, array $values): array
    {
        $this->assertKnown($name);
        $current = $this->section($name);
        $merged = array_replace($current, $values);
        $this->write($name, $merged);
        return $merged;
    }

    private function seed(string $name): void
    {
        $this->write($name, $this->defaults($name));
    }

    /** @return array<string, mixed> */
    private function defaults(string $name): array
    {
        $map = [
            'panel'    => 'solar.defaults.panel',
            'pricing'  => 'solar.defaults.pricing',
            'savings'  => 'solar.defaults.savings',
            'branding' => 'solar.defaults.branding',
        ];
        /** @var array<string, mixed> $d */
        $d = config($map[$name], []);
        return $d;
    }

    /** @param array<string, mixed> $data */
    private function write(string $name, array $data): void
    {
        $dir = $this->root();
        $this->disk()->makeDirectory($dir);
        $this->disk()->put($dir.'/'.$name.'_defaults.txt', TextKv::render($data));
        $this->disk()->put(
            $dir.'/'.$name.'_defaults.json',
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    private function assertKnown(string $name): void
    {
        if (!in_array($name, self::SECTIONS, true)) {
            throw new \InvalidArgumentException("Unknown config section: {$name}");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal `key: value` text serializer used for the human-readable .txt files
 * required by the MVP. Values are coerced to strings; nested arrays become a
 * readable indented block. Scalars round-trip through parse(); nested blocks
 * do not — callers that need exact round-tripping should use JSON instead.
 */
final class TextKv
{
    /** @param array<string, mixed> $data */
    public static function render(array $data): string
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[] = self::line((string) $key, $value, 0);
        }
        return implode("\n", $out)."\n";
    }

    private static function line(string $key, mixed $value, int $depth): string
    {
        $indent = str_repeat('  ', $depth);

        if (is_array($value)) {
            $lines = ["{$indent}{$key}:"];
            $isList = array_is_list($value);
            foreach ($value as $k => $v) {
                $childKey = $isList ? '- '.(is_scalar($v) ? (string) $v : '') : (string) $k;
                if ($isList && is_scalar($v)) {
                    $lines[] = str_repeat('  ', $depth + 1).$childKey;
                } else {
                    $lines[] = self::line($childKey, $v, $depth + 1);
                }
            }
            return implode("\n", $lines);
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif ($value === null) {
            $value = '';
        } elseif (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
            if ($value === '' || $value === '-') {
                $value = '0';
            }
        }

        return "{$indent}{$key}: {$value}";
    }

    /**
     * Parse a flat `key: value` text file. Nested blocks are returned as raw strings.
     *
     * @return array<string, string>
     */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if ($line === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            if ($k === '' || str_starts_with($k, '-') || str_starts_with($k, ' ')) {
                continue;
            }
            $out[$k] = trim($v);
        }
        return $out;
    }
}

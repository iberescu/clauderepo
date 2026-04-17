<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zero-dependency PDF 1.4 writer for sales proposals.
 *
 * Supports: built-in Helvetica (regular + bold), auto page breaks, titles,
 * paragraphs, two-column label/value rows, and a simple ruled-row table.
 * It is deliberately minimal — we own every byte so the MVP has no runtime
 * dependency on dompdf / mPDF.
 */
final class MinimalPdf
{
    private const PAGE_WIDTH  = 595.28;  // A4 in points
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN_X    = 48.0;
    private const MARGIN_TOP  = 56.0;
    private const MARGIN_BOT  = 56.0;

    /** @var list<string> */
    private array $pages = [];
    private string $current = '';
    private float  $cursorY;

    public function __construct()
    {
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN_TOP;
    }

    public function title(string $text, float $size = 22.0): self
    {
        $this->ensureRoom($size + 10);
        $this->cursorY -= $size;
        $this->current .= $this->textOp($text, self::MARGIN_X, $this->cursorY, $size, true);
        $this->cursorY -= 6;
        return $this;
    }

    public function heading(string $text, float $size = 13.0): self
    {
        $this->ensureRoom($size + 14);
        $this->cursorY -= $size + 6;
        $this->current .= $this->textOp($text, self::MARGIN_X, $this->cursorY, $size, true);
        $this->cursorY -= 4;
        $this->current .= $this->ruleOp(self::MARGIN_X, $this->cursorY, self::PAGE_WIDTH - self::MARGIN_X);
        $this->cursorY -= 6;
        return $this;
    }

    public function paragraph(string $text, float $size = 10.5): self
    {
        $maxWidth = self::PAGE_WIDTH - 2 * self::MARGIN_X;
        foreach ($this->wrap($text, $size, $maxWidth, false) as $line) {
            $this->ensureRoom($size + 3);
            $this->cursorY -= $size;
            $this->current .= $this->textOp($line, self::MARGIN_X, $this->cursorY, $size, false);
            $this->cursorY -= 2;
        }
        $this->cursorY -= 4;
        return $this;
    }

    public function kv(string $label, string $value, float $size = 10.5): self
    {
        $this->ensureRoom($size + 4);
        $this->cursorY -= $size;
        $labelCol = self::MARGIN_X;
        $valueCol = self::MARGIN_X + 190.0;
        $this->current .= $this->textOp($label, $labelCol, $this->cursorY, $size, true);
        $this->current .= $this->textOp($value, $valueCol, $this->cursorY, $size, false);
        $this->cursorY -= 3;
        return $this;
    }

    /**
     * Render a ruled table. Each row is [col1, col2, ..]. Columns are evenly
     * distributed. The first row is rendered bold as a header.
     *
     * @param list<list<string>> $rows
     */
    public function table(array $rows, float $size = 9.5): self
    {
        if ($rows === []) {
            return $this;
        }
        $cols = count($rows[0]);
        $usable = self::PAGE_WIDTH - 2 * self::MARGIN_X;
        $colW = $usable / $cols;
        $rowH = $size + 4;

        foreach ($rows as $r => $row) {
            $this->ensureRoom($rowH + 2);
            $isHeader = ($r === 0);
            $y = $this->cursorY - $size;
            for ($c = 0; $c < $cols; $c++) {
                $text = (string)($row[$c] ?? '');
                $x = self::MARGIN_X + $c * $colW + 2;
                $this->current .= $this->textOp($text, $x, $y, $size, $isHeader);
            }
            $this->cursorY -= $rowH;
            $this->current .= $this->ruleOp(self::MARGIN_X, $this->cursorY + 1, self::PAGE_WIDTH - self::MARGIN_X, $isHeader ? 0.8 : 0.2);
        }
        $this->cursorY -= 6;
        return $this;
    }

    public function spacer(float $px = 10.0): self
    {
        $this->cursorY -= $px;
        if ($this->cursorY < self::MARGIN_BOT) {
            $this->newPage();
        }
        return $this;
    }

    public function newPage(): self
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = '';
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN_TOP;
        return $this;
    }

    public function build(): string
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }
        if ($this->pages === []) {
            $this->pages[] = '';
        }
        return $this->assemble();
    }

    // -----------------------------------------------------------------------

    private function ensureRoom(float $needed): void
    {
        if ($this->cursorY - $needed < self::MARGIN_BOT) {
            $this->newPage();
        }
    }

    private function textOp(string $text, float $x, float $y, float $size, bool $bold): string
    {
        $font = $bold ? '/F2' : '/F1';
        $safe = $this->escape($text);
        return sprintf("BT %s %.2f Tf %.2f %.2f Td (%s) Tj ET\n", $font, $size, $x, $y, $safe);
    }

    private function ruleOp(float $x1, float $y, float $x2, float $width = 0.4): string
    {
        return sprintf("%.2f w %.2f %.2f m %.2f %.2f l S\n", $width, $x1, $y, $x2, $y);
    }

    private function escape(string $s): string
    {
        $s = str_replace(["\\", '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $s);
        // Strip characters outside the printable WinAnsi range we can encode
        // safely without a custom encoding dictionary.
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($s[$i]);
            if ($c >= 32 && $c <= 126) {
                $out .= $s[$i];
            } else {
                $out .= '?';
            }
        }
        return $out;
    }

    /** Approximate width of Helvetica at given size (avg 0.5em). */
    private function stringWidth(string $s, float $size): float
    {
        return strlen($s) * $size * 0.52;
    }

    /**
     * Wrap on word boundaries. Returns list of rendered lines. If the caller
     * passed a bold title they can still use this because width coefficient
     * is close enough.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $size, float $maxWidth, bool $bold): array
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) ?: [''] as $para) {
            $words = preg_split('/\s+/', trim($para)) ?: [];
            $line = '';
            foreach ($words as $w) {
                $candidate = $line === '' ? $w : $line.' '.$w;
                if ($this->stringWidth($candidate, $size) > $maxWidth && $line !== '') {
                    $lines[] = $line;
                    $line = $w;
                } else {
                    $line = $candidate;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    private function assemble(): string
    {
        $objects = [];

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $pageCount = count($this->pages);
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (3 + $i * 2).' 0 R';
        }
        $objects[2] = sprintf(
            "<< /Type /Pages /Kids [%s] /Count %d >>",
            implode(' ', $kids),
            $pageCount,
        );

        $fontRef = "/F1 ".(3 + $pageCount * 2)." 0 R /F2 ".(4 + $pageCount * 2)." 0 R";
        for ($i = 0; $i < $pageCount; $i++) {
            $pageId = 3 + $i * 2;
            $contentId = $pageId + 1;
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << %s >> >> >>",
                self::PAGE_WIDTH, self::PAGE_HEIGHT, $contentId, $fontRef,
            );
            $stream = $this->pages[$i];
            $objects[$contentId] = sprintf(
                "<< /Length %d >>\nstream\n%sendstream",
                strlen($stream),
                $stream,
            );
        }

        $fontRegId = 3 + $pageCount * 2;
        $fontBoldId = $fontRegId + 1;
        $objects[$fontRegId]  = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBoldId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        ksort($objects);
        $out = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $total = max(array_keys($objects)) + 1;
        $out .= "xref\n0 {$total}\n";
        $out .= sprintf("%010d %05d f \n", 0, 65535);
        for ($i = 1; $i < $total; $i++) {
            $out .= sprintf("%010d %05d n \n", $offsets[$i] ?? 0, 0);
        }
        $out .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", $total, $xrefOffset);
        return $out;
    }
}

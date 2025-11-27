<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

use PhpLayout\Ast\Grid;
use PhpLayout\Ast\GridCell;
use PhpLayout\Ast\GridRow;

/**
 * Parses ASCII box-drawing grids into Grid objects.
 *
 * Handles grids like:
 * +----------+------------------+---------+
 * |  logo    |       nav        |  auth   |
 * +----------+------------------+---------+
 */
final class GridParser
{
    /**
     * @param list<string> $lines
     */
    public function parse(array $lines): Grid
    {
        $lines = $this->normalizeLines($lines);

        if ($lines === []) {
            return new Grid([]);
        }

        $columnBoundaries = $this->findColumnBoundaries($lines);
        $rows = $this->extractRows($lines, $columnBoundaries);

        return new Grid($rows);
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function normalizeLines(array $lines): array
    {
        // Remove empty lines and trim
        $normalized = [];
        foreach ($lines as $line) {
            $trimmed = rtrim($line);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }
        return $normalized;
    }

    /**
     * Find column boundaries by analyzing the border lines.
     *
     * @param list<string> $lines
     * @return list<int>
     */
    private function findColumnBoundaries(array $lines): array
    {
        // Find a border line (starts with +)
        $borderLine = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, '+')) {
                $borderLine = $line;
                break;
            }
        }

        if ($borderLine === null) {
            return [];
        }

        // Find all + positions (column boundaries)
        $boundaries = [];
        $length = strlen($borderLine);
        for ($i = 0; $i < $length; $i++) {
            if ($borderLine[$i] === '+') {
                $boundaries[] = $i;
            }
        }

        return $boundaries;
    }

    /**
     * Extract rows from content lines (lines starting with |).
     *
     * @param list<string> $lines
     * @param list<int> $columnBoundaries
     * @return list<GridRow>
     */
    private function extractRows(array $lines, array $columnBoundaries): array
    {
        if (count($columnBoundaries) < 2) {
            return [];
        }

        $rows = [];

        foreach ($lines as $line) {
            // Only process content lines (start with |)
            if (!str_starts_with($line, '|')) {
                continue;
            }

            $cells = $this->extractCellsFromLine($line, $columnBoundaries);
            if ($cells !== []) {
                $rows[] = new GridRow($cells);
            }
        }

        return $rows;
    }

    /**
     * Extract cells from a content line.
     *
     * @param list<int> $columnBoundaries
     * @return list<GridCell>
     */
    private function extractCellsFromLine(string $line, array $columnBoundaries): array
    {
        $cells = [];
        $boundaryCount = count($columnBoundaries);

        $i = 0;
        while ($i < $boundaryCount - 1) {
            $start = $columnBoundaries[$i];
            $end = $columnBoundaries[$i + 1];

            // Extract content between boundaries
            $content = $this->extractCellContent($line, $start, $end);
            $cellName = trim($content);

            if ($cellName === '') {
                $i++;
                continue;
            }

            // Check for column span (same name in adjacent cells)
            $span = 1;
            $j = $i + 1;
            while ($j < $boundaryCount - 1) {
                $nextStart = $columnBoundaries[$j];
                $nextEnd = $columnBoundaries[$j + 1];
                $nextContent = trim($this->extractCellContent($line, $nextStart, $nextEnd));

                // Check if the separator between cells is actually a separator or content
                if ($this->isCellContinuation($line, $columnBoundaries[$j])) {
                    $span++;
                    $j++;
                } else {
                    break;
                }
            }

            $cells[] = new GridCell($cellName, $span);
            $i = $i + $span;
        }

        return $this->mergeContinuousCells($cells, $line, $columnBoundaries);
    }

    /**
     * Extract content between two column positions.
     */
    private function extractCellContent(string $line, int $start, int $end): string
    {
        $length = strlen($line);

        if ($start >= $length) {
            return '';
        }

        $actualEnd = min($end, $length);
        $content = substr($line, $start + 1, $actualEnd - $start - 1);

        return $content;
    }

    /**
     * Check if the character at the boundary position indicates cell continuation.
     */
    private function isCellContinuation(string $line, int $position): bool
    {
        if ($position >= strlen($line)) {
            return false;
        }

        // If there's no | at this position, the cell continues
        return $line[$position] !== '|';
    }

    /**
     * Re-parse the line to properly detect spanning cells.
     *
     * @param list<GridCell> $cells
     * @param list<int> $columnBoundaries
     * @return list<GridCell>
     */
    private function mergeContinuousCells(array $cells, string $line, array $columnBoundaries): array
    {
        // Find actual cell separators in the line
        $separators = [];
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            if ($line[$i] === '|') {
                $separators[] = $i;
            }
        }

        if (count($separators) < 2) {
            return $cells;
        }

        // Extract cells based on actual separators
        $result = [];
        for ($i = 0; $i < count($separators) - 1; $i++) {
            $start = $separators[$i];
            $end = $separators[$i + 1];
            $content = trim(substr($line, $start + 1, $end - $start - 1));

            if ($content === '') {
                continue;
            }

            // Calculate column span based on how many original boundaries this cell crosses
            $span = 0;
            foreach ($columnBoundaries as $boundary) {
                if ($boundary > $start && $boundary < $end) {
                    $span++;
                }
            }
            $span = max(1, $span + 1);

            // Normalize: if span would exceed boundaries, cap it
            $span = min($span, count($columnBoundaries) - 1);

            $result[] = new GridCell($content, $span);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

use PhpLayout\Ast\ColumnBoundary;
use PhpLayout\Ast\Grid;
use PhpLayout\Ast\GridCell;
use PhpLayout\Ast\GridRow;
use PhpLayout\Ast\ResponsiveOperator;
use PhpLayout\Ast\ResponsiveOperatorType;
use PhpLayout\Ast\RowBoundary;

/**
 * Parses ASCII box-drawing grids into Grid objects.
 *
 * Handles grids like:
 * +----------+------------------+---------+
 * |  logo    |       nav        |  auth   |
 * +----------+------------------+---------+
 *
 * With responsive operators:
 * +-----------|------------>>sm------+
 * | nav       | content    | aside   |
 * +-----------|------------|---------+
 */
final class GridParser
{
    /**
     * Regex pattern to match responsive operators.
     * Matches: >>, <<, >, <, ! followed by breakpoint name and optional :target
     */
    private const string OPERATOR_PATTERN = '/(?<operator>>>|<<|>|<|!)(?<breakpoint>[a-zA-Z][a-zA-Z0-9_]*)(?::(?<target>[a-zA-Z][a-zA-Z0-9_]*))?/';

    private OperatorValidator $validator;

    public function __construct()
    {
        $this->validator = new OperatorValidator();
    }

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
        $rowBoundaries = $this->findRowBoundaries($lines);

        // Validate operators for conflicts
        $this->validator->validateColumnBoundaries($columnBoundaries);
        $this->validator->validateRowBoundaries($rowBoundaries);

        $rows = $this->extractRows($lines, $this->getBoundaryPositions($columnBoundaries));

        return new Grid($rows, $columnBoundaries, $rowBoundaries);
    }

    /**
     * @param list<ColumnBoundary> $boundaries
     * @return list<int>
     */
    private function getBoundaryPositions(array $boundaries): array
    {
        return array_map(static fn (ColumnBoundary $b): int => $b->position, $boundaries);
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
     * Find column boundaries by analyzing ALL border lines.
     * Parses responsive operators from all border lines and merges them.
     * This allows grids with spanning cells where some boundaries only appear
     * in certain rows.
     *
     * @param list<string> $lines
     * @return list<ColumnBoundary>
     */
    private function findColumnBoundaries(array $lines): array
    {
        // Collect all unique column positions from ALL border lines
        /** @var array<int, bool> $allPositions */
        $allPositions = [];

        foreach ($lines as $line) {
            if (!str_starts_with($line, '+')) {
                continue;
            }

            $positions = $this->findPlusPositions($line);
            foreach ($positions as $pos) {
                $allPositions[$pos] = true;
            }
        }

        if ($allPositions === []) {
            return [];
        }

        // Sort positions to get them in order
        $positions = array_keys($allPositions);
        sort($positions);

        // Now scan all border lines for responsive operators
        /** @var array<int, list<ResponsiveOperator>> $operatorsByPosition */
        $operatorsByPosition = [];
        foreach ($positions as $pos) {
            $operatorsByPosition[$pos] = [];
        }

        foreach ($lines as $line) {
            if (!str_starts_with($line, '+')) {
                continue;
            }

            $this->parseOperatorsFromBorderLine($line, $positions, $operatorsByPosition);
        }

        // Build ColumnBoundary objects
        $boundaries = [];
        foreach ($positions as $pos) {
            $boundaries[] = new ColumnBoundary($pos, $operatorsByPosition[$pos]);
        }

        return $boundaries;
    }

    /**
     * Find positions of + or | characters in a border line.
     * Both are valid column boundary markers in border lines.
     *
     * @return list<int>
     */
    private function findPlusPositions(string $line): array
    {
        $positions = [];
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            if ($line[$i] === '+' || $line[$i] === '|') {
                $positions[] = $i;
            }
        }
        return $positions;
    }

    /**
     * Parse responsive operators from a border line.
     * Operators appear between column boundaries, attached to the boundary
     * that starts the affected column (the left boundary of the cell).
     *
     * Examples:
     *   +-----------|------------>>sm------+  (operator affects the column starting at the | or >>)
     *   +-----------|------------|>>sm-----+  (operator after |, affects column to the right of that |)
     *   +-----------|>>sm!md---------------+  (combined operators)
     *
     * @param list<int> $allPositions All known column positions from all border lines
     * @param array<int, list<ResponsiveOperator>> $operatorsByPosition Output: operators keyed by position
     */
    private function parseOperatorsFromBorderLine(string $line, array $allPositions, array &$operatorsByPosition): void
    {
        // Find positions that actually exist in THIS border line
        $linePositions = $this->findPlusPositions($line);

        // For each segment between positions in this line, look for operators
        for ($i = 0; $i < count($linePositions) - 1; $i++) {
            $startPos = $linePositions[$i];
            $endPos = $linePositions[$i + 1];

            // Extract the segment between boundaries
            $segment = substr($line, $startPos, $endPos - $startPos + 1);

            // Find all operators in this segment
            $operators = $this->parseOperatorsFromSegment($segment);

            if ($operators !== []) {
                // All operators in this segment affect the column that starts at the
                // left boundary of this segment. Find the first boundary position that
                // is >= startPos (which is the column start boundary).
                $targetPosition = $this->findBoundaryAtOrAfter($allPositions, $startPos);
                if ($targetPosition !== null && isset($operatorsByPosition[$targetPosition])) {
                    $operatorsByPosition[$targetPosition] = array_merge(
                        $operatorsByPosition[$targetPosition],
                        $operators,
                    );
                }
            }
        }
    }

    /**
     * Find the first boundary position that is >= the given position.
     *
     * @param list<int> $positions
     */
    private function findBoundaryAtOrAfter(array $positions, int $start): ?int
    {
        foreach ($positions as $pos) {
            if ($pos >= $start) {
                return $pos;
            }
        }
        return null;
    }

    /**
     * Parse responsive operators from a border segment.
     *
     * @return list<ResponsiveOperator>
     */
    private function parseOperatorsFromSegment(string $segment): array
    {
        $operators = [];

        // Match all operators in the segment
        if (preg_match_all(self::OPERATOR_PATTERN, $segment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = ResponsiveOperatorType::from($match['operator']);
                $breakpoint = $match['breakpoint'];
                $target = $match['target'] ?? null;

                $operators[] = new ResponsiveOperator($type, $breakpoint, $target);
            }
        }

        return $operators;
    }

    /**
     * Find row boundaries (horizontal separators) with their responsive operators.
     *
     * Row boundaries can have operators that affect the entire row transition.
     * These are distinct from column operators which appear in column segments.
     * Currently, we only track row boundaries for their positions - column operators
     * are handled separately in findColumnBoundaries().
     *
     * @param list<string> $lines
     * @return list<RowBoundary>
     */
    private function findRowBoundaries(array $lines): array
    {
        $boundaries = [];

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, '+')) {
                continue;
            }

            // Row boundaries don't collect operators - column operators are
            // parsed separately and associated with column boundaries.
            // In the future, row-specific operators could be added with different syntax.
            $boundaries[] = new RowBoundary($index, []);
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

<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

/**
 * @internal
 */
final class Table implements \Stringable
{
    private array $data = [];
    private bool $headerRow = false;
    private int $rowIndex = 0;
    private array $columnWidths = [];

    public function __construct(private int $indent = 0)
    {
    }

    public function setHeaderRow(array $row): self
    {
        $this->headerRow = true;
        foreach ($row as $col => $content) {
            $this->data[$this->rowIndex][$col] = (string) $content;
        }
        $this->rowIndex++;

        return $this;
    }

    public function addRows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    public function addRow(array $row): self
    {
        foreach (\array_values($row) as $col => $content) {
            $this->data[$this->rowIndex][$col] = (string) $content;
        }
        $this->rowIndex++;

        return $this;
    }

    public function __toString(): string
    {
        $this->calculateColumnWidth();
        $output = '';
        foreach ($this->data as $y => $row) {
            $output .= \str_repeat(' ', $this->indent);
            if ($y === 0 && $this->headerRow) {
                $output .= '<color;fg=green>';
            }
            foreach ($row as $x => $cell) {
                $output .= $this->getCellOutput($x, $row);
            }
            if ($y === 0 && $this->headerRow) {
                $output .= '</>';
            }
            $output .= "\n";
        }

        return $output;
    }

    private function getCellOutput(int $index, array $row): string
    {
        $tagsLen = \strlen($row[$index]) - \strlen(\strip_tags($row[$index]));
        $output = \str_pad($row[$index], $this->columnWidths[$index] + $tagsLen, ' ');

        return \sprintf(' %s ', $output);
    }

    private function calculateColumnWidth(): void
    {
        foreach ($this->data as $row) {
            foreach ($row as $x => $col) {
                $col = \strip_tags($col);
                $this->columnWidths[$x] ??= \mb_strlen($col);
                if (\mb_strlen($col) > $this->columnWidths[$x]) {
                    $this->columnWidths[$x] = \mb_strlen($col);
                }
            }
        }
    }
}

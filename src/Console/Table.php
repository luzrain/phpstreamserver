<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

final class Table implements \Stringable
{
    public const SEPARATOR = '%SEPARATOR%';

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

    public function addRow(array|string $row): self
    {
        if ($row === self::SEPARATOR) {
            $this->data[$this->rowIndex] = self::SEPARATOR;
        } else {
            foreach (array_values($row) as $col => $content) {
                $this->data[$this->rowIndex][$col] = (string) $content;
            }
        }
        $this->rowIndex++;

        return $this;
    }

    public function __toString(): string
    {
        $this->calculateColumnWidth();
        $output = '';
        foreach ($this->data as $y => $row) {
            $output .= str_repeat(' ', $this->indent);
            if ($row === self::SEPARATOR) {
                $output .= $this->getSeparator();
            } else {
                if ($y === 0 && $this->headerRow) {
                    $output .= '<color;fg=green>';
                }
                foreach ($row as $x => $cell) {
                    $output .= $this->getCellOutput($x, $row);
                }
                if ($y === 0 && $this->headerRow) {
                    $output .= '</>';
                }
            }
            $output .= "\n";
        }

        return $output;
    }

    private function getSeparator(): string
    {
        $output = str_repeat(' ', $this->indent);
        $columnCount = count($this->data[0]);
        for ($index = 0; $index < $columnCount; $index++) {
            $output .= str_pad('-', $this->columnWidths[$index] + 2, '-');
        }

        return $output;
    }

    private function getCellOutput(int $index, array $row): string
    {
        $tagsLen = strlen($row[$index]) - strlen(strip_tags($row[$index]));
        $output = str_pad($row[$index], $this->columnWidths[$index] + $tagsLen, ' ');

        return sprintf(' %s ', $output);
    }

    private function calculateColumnWidth(): void
    {
        foreach ($this->data as $row) {
            if ($row === self::SEPARATOR) {
                continue;
            }
            foreach ($row as $x => $col) {
                $col = strip_tags($col);
                $this->columnWidths[$x] ??= mb_strlen($col);
                if (mb_strlen($col) > $this->columnWidths[$x]) {
                    $this->columnWidths[$x] = mb_strlen($col);
                }
            }
        }
    }
}

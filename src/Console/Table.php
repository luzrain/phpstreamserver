<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

final class Table implements \Stringable
{
    public const SEPARATOT = '%SEPARATOR%';

    private array $data = [];
    private bool $header = false;
    private int $rowIndex = 0;
    private array $columnWidths = [];
    private string|null $headerTitle = null;

    public function __construct(private bool $border = true, private int $indent = 0)
    {
    }

    public function setHeaderTitle(string $headerTitle): self
    {
        $this->headerTitle = $headerTitle;

        return $this;
    }

    public function setHeaders(array $row): self
    {
        $this->header = true;
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
        if ($row === self::SEPARATOT) {
            $this->data[$this->rowIndex] = self::SEPARATOT;
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
        if ($this->headerTitle !== null) {
            $output .= $this->getHeader($this->headerTitle);
        } elseif ($this->border) {
            $output .= $this->getBorderLine();
        }
        foreach ($this->data as $y => $row) {
            if ($row === self::SEPARATOT) {
                $output .= $this->getBorderLine();
            } else {
                foreach ($row as $x => $cell) {
                    $output .= $this->getCellOutput($x, $row);
                }
                $output .= "\n";
            }
            if ($y === 0 && $this->header) {
                $output .= $this->getBorderLine();
            }
        }

        $output .= $this->border ? $this->getBorderLine() : '';

        return $output;
    }

    private function getHeader(string $title): string
    {
        $output = trim($this->getBorderLine());
        $output = str_replace('-', ' ', $output);
        $headerLen = strlen($title) + 2;
        $len = strlen($output) - $headerLen;
        $half = (int) floor($len / 2);
        $str = substr($output, 0, $half) . ' ' . $title . ' ' . substr($output, $half + $headerLen);

        return "\033[47;30m" . $str . "\033[0m\n";
    }

    private function getBorderLine(): string
    {
        $output = '';
        $columnCount = count($this->data[0]);

        for ($col = 0; $col < $columnCount; $col++) {
            $output .= $this->getCellOutput($col);
        }

        if ($this->border) {
            $output .= '+';
        }

        return $output . "\n";
    }

    private function getCellOutput(int $index, array|null $row = null): string
    {
        $output = '';

        if ($index === 0) {
            $output .= str_repeat(' ', $this->indent);
        }

        if ($this->border) {
            $output .= $row ? '|' : '+';
        }

        $output .= $row ? ' ' : '-';
        $output .= str_pad($row[$index] ?? '-', $this->columnWidths[$index], $row ? ' ' : '-');
        $output .= $row ? ' ' : '-';

        if ($row !== null && $index === count($row) - 1 && $this->border) {
            $output .= $row ? '|' : '+';
        }

        return $output;
    }

    private function calculateColumnWidth(): void
    {
        foreach ($this->data as $row) {
            if ($row === self::SEPARATOT) {
                continue;
            }
            foreach ($row as $x => $col) {
                $this->columnWidths[$x] ??= mb_strlen($col);
                if (mb_strlen($col) > $this->columnWidths[$x]) {
                    $this->columnWidths[$x] = mb_strlen($col);
                }
            }
        }
    }
}

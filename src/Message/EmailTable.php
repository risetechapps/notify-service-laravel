<?php

namespace RiseTechApps\Notify\Message;

class EmailTable
{
    /**
     * @var array<int, string>
     */
    private array $headers = [];

    /**
     * @var array<int, array<int|string, mixed>>
     */
    private array $rows = [];

    public static function make(): self
    {
        return new self();
    }

    public function headers(array $headers): self
    {
        $this->headers = array_values($headers);

        return $this;
    }

    public function addRow(array $row): self
    {
        if ($this->shouldInferHeaders($row)) {
            $this->headers = array_keys($row);
        }

        $this->rows[] = $row;

        return $this;
    }

    /**
     * @param array<int, array<int|string, mixed>> $rows
     */
    public function rows(array $rows): self
    {
        $this->rows = [];

        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    public function toArray(): array
    {
        $rows = array_map($this->normalizeRow(...), $this->rows);

        $table = [
            'rows' => $rows,
        ];

        if ($this->headers !== []) {
            $table['headers'] = $this->headers;
        }

        return $table;
    }

    private function normalizeRow(array $row): array
    {
        if ($this->headers === []) {
            return array_values($row);
        }

        if ($this->isAssoc($row)) {
            $normalized = [];

            foreach ($this->headers as $header) {
                $normalized[] = $row[$header] ?? '';
            }

            return $normalized;
        }

        $row = array_values($row);
        $headerCount = count($this->headers);

        if (count($row) < $headerCount) {
            $row = array_pad($row, $headerCount, '');
        }

        if (count($row) > $headerCount) {
            $row = array_slice($row, 0, $headerCount);
        }

        return $row;
    }

    private function shouldInferHeaders(array $row): bool
    {
        return $this->headers === [] && $this->isAssoc($row);
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

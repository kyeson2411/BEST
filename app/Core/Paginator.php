<?php

declare(strict_types=1);

namespace Sibol\Core;

final class Paginator
{
    public int $total;
    public int $page;
    public int $perPage;

    public function __construct(int $total, int $page, int $perPage = 25)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->page = max(1, min($page, $this->pages()));
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /** SQL fragment with integers only. */
    public function limitSql(): string
    {
        return ' LIMIT ' . $this->perPage . ' OFFSET ' . $this->offset();
    }
}

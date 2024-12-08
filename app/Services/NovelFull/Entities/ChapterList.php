<?php

namespace App\Services\NovelFull\Entities;

class ChapterList
{
    public function __construct(
        public string $novel,
        public int $page,
        public int $totalPages,
        public array $chapters,
    )
    {
    }
}

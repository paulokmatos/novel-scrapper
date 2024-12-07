<?php

namespace App\Services\NovelFull\Entities;

class ChapterList
{
    public function __construct(
        public Novel $novel,
        public int $page,
        public int $pageSize,
        public int $totalPages,
    )
    {
    }
}

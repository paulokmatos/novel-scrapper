<?php

namespace App\Services\NovelFull\Entities;

readonly class Novel
{
    public function __construct(
        public string $title,
        public string $author,
        public string $uri,
        public ?string $description = null,
    ) {
    }
}

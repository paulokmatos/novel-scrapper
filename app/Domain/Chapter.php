<?php

namespace App\Domain;

class Chapter
{
    public function __construct(
        public string $title,
        public string $content,
    ) {
    }
}

<?php

namespace App\Services\NovelFull\Entities;

use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\DomCrawler\Crawler;

readonly class Novel
{
    public function __construct(
        public string $title,
        public string $author,
        public string $uri,
        public ?string $cover = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?array $chapters = [],
    ) {
    }

    /**
     * @throws LargeTextException
     * @throws RateLimitException
     * @throws TranslationRequestException
     */
    public static function fromUrl(string $url): self
    {
        $content = file_get_contents($url);
        $crawler = new Crawler($content);

        $title = $crawler->filter('h3.title')->text();
        $author = str_replace('Author:', '', trim($crawler->filter('div.info div')->first()->text()));
        $status = str_replace('Status:', '', trim($crawler->filter('div.info div')->last()->text()));
        $imageUrl = $crawler->filter('meta[name="image"]')->attr('content');

        $description = $crawler->filter('div.desc-text p')->text();

        return new self(
            title: $title,
            author: $author,
            uri: $url,
            cover: $imageUrl,
            description: $description,
            status: $status
        );
    }
}

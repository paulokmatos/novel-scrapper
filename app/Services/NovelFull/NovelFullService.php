<?php

namespace App\Services\NovelFull;

use App\Domain\Chapter;
use App\Services\NovelFull\Entities\ChapterList;
use App\Services\NovelFull\Entities\Novel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\DomCrawler\Crawler;

class NovelFullService
{

    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://novelfull.com';
    }

    public function listNovels(string $category = 'popular', ?int $perPage = 10, ?int $page = 1): LengthAwarePaginator
    {
        $fullUrl = $this->baseUrl . match ($category) {
                'latest' => '/latest-release-novel',
                'completed' => '/completed-novel',
                'hot' => '/hot-novel',
                default => '/most-popular'
            };
        $page = $page ?? 1;
        $perPage = $perPage ?? 10;

        $html = file_get_contents($fullUrl . "?page=$page");
        $crawler = new Crawler($html);

        $totalPages = $this->getTotalPages($crawler);
        $novels = $crawler->filter('div.col-xs-7')->each(function (Crawler $node) {
            $title = $node->filter('h3.truyen-title')->text();
            $author = $node->filter('span.author')->text();
            $uri = $node->filter('a')->attr('href');

            return new Novel($title, $author, $uri);
        });

        $totalItems = $totalPages * $perPage;

        return new LengthAwarePaginator(
            items: $novels,
            total: $totalItems,
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => request()->url(),
            ]
        );
    }

    public function listChapters(Novel $novel, $page = 1): ChapterList
    {
        $html = file_get_contents($novel->uri . "?page=$page");

        $crawler = new Crawler($html);

        $chapters = $crawler->filter('#list-chapter')
            ->filter('ul.list-chapter li > a')
            ->each(function (Crawler $node) {
                return $node->text();
            });

        return new ChapterList(
            novel: $novel->title,
            page: $page,
            totalPages: $this->getTotalPages($crawler),
            chapters: $chapters
        );
    }

    /**
     * @throws LargeTextException
     * @throws RateLimitException
     * @throws TranslationRequestException
     */
    public function parseChapters(Novel $novel, $page = 1, $amount = 5, $offset = 1): array
    {
        $tr = new GoogleTranslate('pt_br');
        $tr->setSource('en');

        $crawler = new Crawler();
        $novelPage = file_get_contents($novel->uri . "?page=$page");
        $crawler->addHtmlContent($novelPage);
        $chapters = [];

        $chaptersUrl = $crawler->filter('#list-chapter')
            ->filter('ul.list-chapter li > a')
            ->slice($offset - 1, $amount)
            ->each(function (Crawler $node) {
                return $node->attr('href');
            });

        foreach ($chaptersUrl as $chapterUrl) {
            $crawler->clear();
            $html = file_get_contents($this->baseUrl . $chapterUrl);
            $crawler->addHtmlContent($html);
            $title = $crawler->filter('h2 a.chapter-title')->text();

            $chapterContent = $crawler->filter('#chapter-content')
                ->filter('p')
                ->each(function (Crawler $node) {
                    return $node->text() ?: "‎";
                });

            $translatedContent = [];
            $blockSize = 15000;
            foreach ($chapterContent as $paragraph) {
                $chunks = str_split($paragraph, $blockSize);
                foreach ($chunks as $chunk) {
                    $translatedContent[] = $tr->translate($chunk);
                }
            }

            $fullTranslatedContent = implode("\n", $translatedContent);

            $chapters[] = new Chapter(
                $tr->translate($title),
                $fullTranslatedContent
            );
        }

        return $chapters;
    }


    /**
    /**
     * @param Crawler $crawler
     * @return string|null
     */
    public function getTotalPages(Crawler $crawler): ?string
    {
        return $crawler->filter('.pagination li.last a')->last()->attr('data-page') + 1;
    }
}

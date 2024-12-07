<?php

namespace App\Services\NovelFull;

use App\Services\NovelFull\Entities\Novel;
use Illuminate\Support\Collection;
use Symfony\Component\DomCrawler\Crawler;

class NovelFullService
{

    protected string $baseUrl;

    public function __construct($url)
    {
        if(!str_contains($url, 'https://novelfull.com')) {
            throw new \RuntimeException("Novel precisa pertencer ao site novelfull.com");
        }

        $this->baseUrl = $url;
    }

    public function novels($page = 1): Collection
    {
        $url = $this->baseUrl . "/hot-novel?page=$page";

        $html = file_get_contents($url);

        $crawler = new Crawler($html);

        $novels = $crawler->filter('div.col-xs-7')->each(function (Crawler $node) {
            $title = $node->filter('h3.truyen-title')->text();
            $author = $node->filter('span.author')->text();
            $uri = $node->filter('a')->attr('href');

            return new Novel($title, $author, $uri);
        });

        return collect($novels);
    }

    public function chapterList(Novel $novel)
    {
        $novel->uri;

        $html = file_get_contents($novel->uri);

        $crawler = new Crawler($html);

        $title = $crawler->filter('a.truyen-title')->text();
        $chapterTitle = $crawler->filter('.chapter-text')->text();

        $chapterContent = $crawler->filter('#chapter-content')
            ->filter('p')
            ->each(function (Crawler $node, $i) use (&$fullText) {
                if($node->text() == '') {
                    return "";
                }
                return  $node->text();
            });
    }
}

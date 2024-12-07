<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;

class NovelParserController
{
    public function parse(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->get('url');

    }
}

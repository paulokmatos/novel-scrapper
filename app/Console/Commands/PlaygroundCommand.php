<?php

namespace App\Console\Commands;

use App\Services\NovelFull\Entities\Novel;
use App\Services\NovelFull\NovelFullService;
use Illuminate\Console\Command;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;

class PlaygroundCommand extends Command
{
    protected $signature = 'playground {--url=}';

    protected $description = 'Command description';

    /**
     * @throws LargeTextException
     * @throws RateLimitException
     * @throws TranslationRequestException
     */
    public function handle(): void
    {
        $url = $this->option('url');
        $service = new NovelFullService();

        $novel = Novel::fromUrl($url);

        $service->parseChapters($novel);
    }
}

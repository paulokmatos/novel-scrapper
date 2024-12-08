<?php

namespace App\Jobs;

use App\Epub\Epub;
use App\Services\NovelFull\NovelFullService;
use App\Services\NovelFull\Entities\Novel;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;

class GenerateEpubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $novelUrl,
        protected int $page = 1,
        protected int $amount = 5,
        protected int $offset = 1
    ) {
    }

    /**
     * @throws LargeTextException
     * @throws RateLimitException
     * @throws TranslationRequestException
     * @throws Exception
     */
    public function handle(): void
    {
        $novel = Novel::fromUrl($this->novelUrl);
        $offset = $this->offset;
        $amount = $this->amount;

        if($this->page > 1){
            $offset = $this->offset * $this->page + 1;
            $amount = $this->amount * $this->page;
        }

        $service = new NovelFullService();
        $chapters = $service->parseChapters($novel, $this->page, $this->amount, $this->offset);
        $title = strtoupper($novel->title) . "_FROM_{$amount}_TO_{$offset}";

        $epub = new Epub();
        $epub->generate($title, $chapters);
    }
}
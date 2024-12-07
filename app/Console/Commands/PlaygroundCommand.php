<?php

namespace App\Console\Commands;

use App\Services\NovelFull\NovelFullService;
use Illuminate\Console\Command;

class PlaygroundCommand extends Command
{
    protected $signature = 'playground {--page=}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $page = $this->option('page');
        $service = new NovelFullService();

        $chapters = $service->chapters();

        dump($service->novels($page));
    }
}

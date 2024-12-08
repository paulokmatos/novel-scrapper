<?php

namespace App\Http\Controllers;

use App\Epub\Epub;
use App\Jobs\GenerateEpubJob;
use App\Services\NovelFull\Entities\Novel;
use App\Services\NovelFull\NovelFullService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;

class NovelParserController
{
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'category' => ['nullable', 'string', 'in:hot,completed,latest,popular'],
            'page' => ['nullable', 'numeric', 'min:1']
        ]);
        $page = $request->input('page');
        $category = $request->input('category', 'latest');

        $service = new NovelFullService();

        return response()->json($service->listNovels(category: $category, page: $page));
    }

    /**
     * @throws LargeTextException
     * @throws RateLimitException
     * @throws TranslationRequestException
     */
    public function listChapters(Request $request): JsonResponse
    {
        $request->validate([
            'novel_url' => ['required', 'string', 'url'],
            'page' => ['nullable', 'numeric', 'min:1']
        ]);

        $novelUrl = $request->input('novel_url');
        $page = $request->input('page', 1);

        $service = new NovelFullService();

        $chapters = $service->listChapters(Novel::fromUrl($novelUrl), $page);

        return response()->json($chapters);
    }

    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'novel_url' => ['required', 'string', 'url'],
            'email' => ['required', 'string', 'email'],
            'page' => ['nullable', 'numeric', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'from' => ['nullable', 'numeric', 'min:1']
        ]);
        $novelUrl = $request->input('novel_url');
        $email = $request->input('email');
        $page = $request->input('page', 1);
        $amount = $request->input('amount', 5);
        $offset = $request->input('from', 1);

        GenerateEpubJob::dispatch($novelUrl, $email, $page, $amount, $offset);

        return response()->json(['message' => 'EPUB generation is in progress. You will receive an email shortly.']);

    }
}

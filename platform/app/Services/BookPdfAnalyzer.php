<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Models\Media;
use Symfony\Component\Process\Process;

class BookPdfAnalyzer
{
    public function extract(Media $media): string
    {
        $location = app(MediaOriginalLocator::class)->find($media);
        if (! $location) throw new \RuntimeException('PDF source unavailable.');
        $process = new Process([config('platform.media_pdftotext_binary', 'pdftotext'), '-layout', app(MediaOriginalLocator::class)->path($location), '-']);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) throw new \RuntimeException('PDF text extraction failed.');
        $text = trim($process->getOutput());
        if ($text === '') throw new \RuntimeException('PDF contains no selectable text.');
        return mb_substr($text, 0, 120000);
    }

    public function analyze(string $text): array
    {
        return app(AiProviderInterface::class)->analyzeBookPdf([
            'locale' => app()->getLocale(),
            'text' => $text,
        ]);
    }
}

<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Models\Media;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BookPdfAnalyzer
{
    public function extract(Media $media): string
    {
        $location = app(MediaOriginalLocator::class)->find($media);
        if (! $location) throw new \RuntimeException('PDF source unavailable.');
        $binary = $this->findPdfToTextBinary();
        $process = new Process([$binary, '-layout', app(MediaOriginalLocator::class)->path($location), '-']);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput());
            throw new \RuntimeException('PDF text extraction failed.'.($detail !== '' ? ' '.mb_substr($detail, 0, 400) : ''));
        }
        $text = trim($process->getOutput());
        if ($text === '') throw new \RuntimeException('PDF contains no selectable text.');
        return mb_substr($text, 0, 120000);
    }

    private function findPdfToTextBinary(): string
    {
        $configured = trim((string) config('platform.media_pdftotext_binary', 'pdftotext'));
        $finder = new ExecutableFinder();
        foreach (array_values(array_unique([$configured, '/usr/bin/pdftotext', '/usr/local/bin/pdftotext'])) as $candidate) {
            if ($candidate === '') continue;
            if (strpbrk($candidate, '/\\') !== false && is_executable($candidate)) return $candidate;
            $found = $finder->find($candidate);
            if ($found) return $found;
        }
        throw new \RuntimeException('PDF text extraction is unavailable: pdftotext is not installed. Install poppler-utils or set MEDIA_PDFTOTEXT_BINARY to its executable path.');
    }

    public function analyze(string $text): array
    {
        return app(AiProviderInterface::class)->analyzeBookPdf([
            'locale' => app()->getLocale(),
            'text' => $text,
        ]);
    }
}

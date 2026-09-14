<?php
namespace App\Services;
/** Reuses the existing per-hop DNS-pinned transport, with smaller text limits. */
class PublicDocumentDownload extends PublicImageDownload
{
    public const MAX_BYTES=2*1024*1024;
    public function target(string $url):array{if(parse_url($url,PHP_URL_SCHEME)!=='https')throw new \RuntimeException('HTTPS is required.');return parent::target($url);}
    protected function validateFile(string $path):string
    {
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if(!in_array($mime,['text/plain','text/html','text/xml','application/xml','application/rss+xml','application/atom+xml'],true))throw new \RuntimeException('Unsupported external document.');
        return $mime;
    }
}

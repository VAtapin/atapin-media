<?php

namespace App\Services;

class ExternalPollUrl
{
    public function valid(?string $url): bool
    {
        if (!$url || !filter_var($url,FILTER_VALIDATE_URL)) return false;
        $parts=parse_url($url);
        $host=strtolower($parts['host']??'');
        return ($parts['scheme']??'')==='https' && str_contains($host,'.') && $host!=='localhost'
            && !str_ends_with($host,'.localhost') && !filter_var(trim($host,'[]'),FILTER_VALIDATE_IP)
            && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port'])||$parts['port']===443);
    }
    public function embeddable(?string $url): bool
    {
        if (!$this->valid($url)) return false;
        $host=strtolower(parse_url($url,PHP_URL_HOST));
        return $host!==strtolower(parse_url(config('app.url'),PHP_URL_HOST)??'')
            && in_array($host,array_map('strtolower',config('polls.allowed_embed_hosts',[])),true);
    }
}

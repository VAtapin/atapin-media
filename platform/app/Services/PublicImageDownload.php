<?php
namespace App\Services;

use GuzzleHttp\Psr7\{Uri,UriResolver};
use Illuminate\Support\Facades\Http;

/** Resolve and pin each hop: a redirect/DNS rebinding must not reach private services. */
class PublicImageDownload
{
    public const MAX_BYTES=12*1024*1024;
    public function addresses(string $host): array
    {
        if(filter_var($host,FILTER_VALIDATE_IP))return [$host];
        $records=dns_get_record($host,DNS_A|DNS_AAAA);
        return array_values(array_filter(array_map(fn($r)=>$r['ip']??$r['ipv6']??null,$records?:[])));
    }
    public function target(string $url): array
    {
        $parts=parse_url($url);
        if(!$parts||!in_array($parts['scheme']??null,['https','http'],true)||isset($parts['user'])||isset($parts['pass']))throw new \RuntimeException('Invalid external image URL.');
        $host=trim($parts['host']??'','[]');$port=$parts['port']??(($parts['scheme']==='https')?443:80);
        if(!$host||!in_array($port,[80,443],true))throw new \RuntimeException('Invalid external image host.');
        $addresses=$this->addresses($host);
        if(!$addresses)throw new \RuntimeException('External image host is unavailable.');
        foreach($addresses as $address) {
            if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)
                || str_starts_with(strtolower($address),'::ffff:') || preg_match('/^(64:ff9b:|2001:db8:|2002:|ff)/i',$address))throw new \RuntimeException('Private external image address is forbidden.');
        }
        $address=$addresses[0];if(str_contains($address,':'))$address='['.$address.']';
        return ['host'=>$host,'port'=>$port,'address'=>$address];
    }
    public function download(string $url): array
    {
        if(!extension_loaded('curl'))throw new \RuntimeException('cURL is required for external images.');
        $path=app(MediaDerivatives::class)->temporary('image');$deadline=microtime(true)+45;
        try {
            for($hop=0;$hop<4;$hop++) {
                $target=$this->target($url);$remaining=(int)ceil($deadline-microtime(true));if($remaining<=0)throw new \RuntimeException('External image timed out.');
                $response=Http::connectTimeout(min(10,$remaining))->timeout($remaining)->withOptions([
                    'allow_redirects'=>false,'verify'=>true,'proxy'=>'','sink'=>$path,
                    'curl'=>[CURLOPT_RESOLVE=>[$target['host'].':'.$target['port'].':'.$target['address']]],
                    'on_headers'=>function($response){$length=$response->getHeaderLine('Content-Length');if(is_numeric($length)&&(int)$length>static::MAX_BYTES)throw new \RuntimeException('External file is too large.');},
                    'progress'=>function($total,$downloaded){if($downloaded>static::MAX_BYTES)throw new \RuntimeException('External file is too large.');},
                ])->get($url);
                if(in_array($response->status(),[301,302,303,307,308],true)){
                    $location=$response->header('Location');if(!$location)throw new \RuntimeException('Invalid image redirect.');
                    $url=(string)UriResolver::resolve(new Uri($url),new Uri($location));continue;
                }
                if(!$response->successful()||!is_file($path)||filesize($path)>static::MAX_BYTES)throw new \RuntimeException('External file download failed.');
                return ['path'=>$path,'mime'=>$this->validateFile($path)];
            }
            throw new \RuntimeException('Too many image redirects.');
        } catch(\Throwable $error){if(is_file($path))unlink($path);throw $error;}
    }
    protected function validateFile(string $path):string
    {
        $dimensions=@getimagesize($path);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if(!$dimensions||!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)||$mime!==$dimensions['mime']||$dimensions[0]*$dimensions[1]>40000000)throw new \RuntimeException('External file is not a supported image.');
        return $mime;
    }
}

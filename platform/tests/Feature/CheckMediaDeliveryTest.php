<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\MediaDeliveryCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class CheckMediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
    }

    private function media(): Media
    {
        Storage::fake('local');
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==');
        Storage::disk('local')->put('check.png', $bytes);
        return Media::create(['source'=>'upload','title'=>'Check','original_name'=>'check.png','kind'=>'image','mime'=>'image/png','disk'=>'local','path'=>'check.png','bytes'=>strlen($bytes),'status'=>'ready','metadata'=>[]]);
    }

    public function test_media_check_reads_local_metadata_and_a_bounded_range(): void
    {
        $media = $this->media();
        Http::fake(['http://localhost/media/check.png' => Http::response('sample',206,['Accept-Ranges'=>'bytes','Content-Range'=>'bytes 0-5/100'])]);

        $report = app(MediaDeliveryCheck::class)->run($media, 'http://localhost/media/check.png');
        $this->assertTrue($report['ok']);
        $this->assertSame('ok', $report['local']['status']);
        $this->assertSame('ok', $report['technical']['status']);
        $this->assertSame('ok', $report['http']['status']);
        $this->assertSame(206, $report['http']['http_status']);
    }

    public function test_media_check_fails_when_the_server_ignores_range_requests(): void
    {
        $media = $this->media();
        Http::fake(['http://localhost/media/check.png' => Http::response(str_repeat('x', 2048),200)]);

        $report = app(MediaDeliveryCheck::class)->run($media, 'http://localhost/media/check.png');
        $this->assertFalse($report['ok']);
        $this->assertSame('failed', $report['http']['status']);
        $this->assertSame(200, $report['http']['http_status']);
    }
}

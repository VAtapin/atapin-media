<?php
namespace Tests\Feature;
use App\Models\ImportRun;
use App\Models\Media;
use App\Services\Importing\ImportCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LocalImportTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/atapin-local-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        config(['platform.import_inbox_root' => $this->root, 'filesystems.disks.import-inbox.root' => $this->root,
            'platform.media_upload_reserve_free_bytes' => 0]);
        Storage::forgetDisk('import-inbox');
    }
    protected function tearDown(): void
    {
        File::deleteDirectory($this->root); parent::tearDown();
    }
    private function runImport(string $source, string $path): ImportRun
    {
        $run = ImportRun::create(['source' => $source, 'source_options' => ['path' => $path]]);
        app(ImportCenter::class)->run($run);
        return $run->fresh();
    }
    public function test_folder_files_have_downloadable_paths_and_repeat_preserves_edits(): void
    {
        mkdir($this->root.'/incoming'); file_put_contents($this->root.'/incoming/test.txt', 'Hallo');
        $this->assertSame('complete', $this->runImport('local-folder', 'incoming')->status);
        $media = Media::firstOrFail();
        $this->assertSame('Hallo', Storage::disk($media->disk)->get($media->path));
        $media->update(['title' => 'Edited']);
        $this->runImport('local-folder', 'incoming');
        $this->assertDatabaseCount('media', 1); $this->assertSame('Edited', $media->fresh()->title);
    }
    public function test_zip_originals_survive_import_and_repeat_does_not_duplicate(): void
    {
        $zip = new ZipArchive; $zip->open($this->root.'/archive.zip', ZipArchive::CREATE);
        $zip->addFromString('nested/test.txt', 'Original'); $zip->close();
        $this->assertSame('complete', $this->runImport('local-archive', 'archive.zip')->status);
        $media = Media::firstOrFail();
        $this->assertSame('Original', Storage::disk($media->disk)->get($media->path));
        $this->runImport('local-archive', 'archive.zip'); $this->assertDatabaseCount('media', 1);
    }
    public function test_zip_traversal_is_rejected_before_registration(): void
    {
        $zip = new ZipArchive; $zip->open($this->root.'/bad.zip', ZipArchive::CREATE);
        $zip->addFromString('../escape.txt', 'Escape'); $zip->close();
        try { $this->runImport('local-archive', 'bad.zip'); $this->fail('Unsafe archive accepted.'); }
        catch (\RuntimeException $error) { $this->assertSame('Unsafe archive entry.', $error->getMessage()); }
        $this->assertDatabaseCount('media', 0); $this->assertDatabaseHas('import_runs', ['status' => 'failed']);
    }
    public function test_tar_original_remains_readable(): void
    {
        $tar = new \PharData($this->root.'/archive.tar'); $tar->addFromString('nested/test.txt', 'Original'); unset($tar);
        $this->runImport('local-archive', 'archive.tar'); $media = Media::firstOrFail();
        $this->assertSame('Original', Storage::disk($media->disk)->get($media->path));
    }
    public function test_content_export_preserves_posts_polls_comments_and_reviewed_edits(): void
    {
        mkdir($this->root.'/export');
        file_put_contents($this->root.'/export/content.json', json_encode(['schema' => 'atapin-content/v1', 'records' => [
            ['source' => 'youtube', 'id' => 'UgPost', 'kind' => 'post', 'title' => 'Beitrag', 'body' => 'Text',
                'poll' => ['choices' => ['Ja', 'Nein']], 'comments' => [['id' => 'C1', 'text' => 'Kommentar', 'author' => 'Name']]],
        ]]));
        $this->runImport('local-folder', 'export');
        $this->assertDatabaseCount('source_records', 3);
        $record = \App\Models\SourceRecord::where('kind', 'post')->firstOrFail();
        $record->update(['title' => 'Edited', 'status' => 'ready']);
        $this->runImport('local-folder', 'export');
        $this->assertSame('Edited', $record->fresh()->title); $this->assertDatabaseCount('source_records', 3);
    }
    public function test_link_adapter_rejects_other_hosts_and_credentials(): void
    {
        $adapter = new \App\Services\Importing\ServiceLinkAdapter('youtube-service');
        foreach (['https://127.0.0.1/test', 'https://youtube.com.evil.test/test', 'https://name@youtube.com/watch?v=abcdefghijk'] as $url) {
            try { $adapter->validate(['source_ref' => $url]); $this->fail('URL accepted.'); }
            catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('source_ref', $error->errors()); }
        }
        $this->assertSame('https://www.youtube.com/@MannaVomHimmel', $adapter->validate(['source_ref' => 'https://www.youtube.com/@MannaVomHimmel'])['source_ref']);
    }
}

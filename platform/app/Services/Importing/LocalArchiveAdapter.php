<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class LocalArchiveAdapter implements ImportAdapter
{
    public function __construct(private ?string $inboxRoot = null)
    {
        $this->inboxRoot ??= config('platform.import_inbox_root');
    }
    public function source(): string { return 'local-archive'; }
    public function validate(array $input): array
    {
        if (empty($input['path']) && empty($input['media_id'])) {
            throw ValidationException::withMessages(['path' => __('imports.archive_required')]);
        }
        return $input;
    }
    public function normalize(array $input): array
    {
        return ['source' => $this->source(), 'source_kind' => $this->source(),
            'source_ref' => $input['path'] ?? $input['media_id'],
            'source_options' => array_intersect_key($input, array_flip(['path', 'media_id'])),
            'target_profile' => $input['target_profile'] ?? 'mixed'];
    }
    public function import(ImportRun $run): void
    {
        app(ImportProgress::class)->checkpoint($run, 'prepare');
        if ($id = $run->source_options['media_id'] ?? null) {
            $original=\App\Models\MediaOriginal::where('media_id',$id)->where('source','upload')->get()->first(fn($original)=>\App\Models\ResumableMediaUpload::where('id',$original->source_id)->where('user_id',$run->user_id)->where('status','complete')->exists());
            $media=Media::findOrFail($id);
            abort_unless($original || ((string)$media->user_id===(string)$run->user_id && $media->source==='upload'),403);
            $location=$original??$media;
            $archive = Storage::disk($location->disk)->path($location->path); $name = $location->original_name;
        } else {
            $archive = ImportPath::resolve($this->inboxRoot, $run->source_options['path'] ?? ''); $name = $archive;
        }
        (new LocalFolderAdapter($this->inboxRoot))->importDirectory($run,$this->expandFile($run,$archive,$name));
    }
    public function expandFile(ImportRun $run,string $archive,string $name): string
    {
        if (! is_file($archive)) throw new RuntimeException('Archive is unavailable.');
        $journal=app(ImportJournal::class); $key='archive:'.$archive;
        $signature=['bytes'=>filesize($archive),'mtime'=>filemtime($archive)];
        $item=$journal->item($run,$key);
        $hash=($item && ($item->metadata['signature']??null)===$signature) ? $item->metadata['sha256'] : hash_file('sha256',$archive);
        $journal->record($run,$key,basename($archive),'archive-entry','pending',null,['signature'=>$signature,'sha256'=>$hash]);
        $root = rtrim($this->inboxRoot, '/\\').'/archives/'.$hash;
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) throw new RuntimeException('Storage unavailable.');
        $lock = fopen($root.'/.extract.lock', 'c+b');
        if (! $lock) throw new RuntimeException('Storage unavailable.');
        if (! flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Archive is being extracted. Retry later.'); }
        try {
            $data = $root.'/files';
            if (! is_file($root.'/.complete')) {
                if (! is_dir($data)) mkdir($data, 0700);
                app(ImportProgress::class)->checkpoint($run, 'extract');
                $this->extract($archive, $name, $data, $run); touch($root.'/.complete');
            }
            $journal->record($run,$key,basename($archive),'archive-entry','complete',null,['signature'=>$signature,'sha256'=>$hash]);
            return $data;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    private function extract(string $archive, string $name, string $root, ImportRun $run): void
    {
        $bytes = 0; $count = 0;
        if (str_ends_with(strtolower($name), '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($archive) !== true) throw new RuntimeException('Cannot open ZIP archive.');
            try {
                $pending=0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    app(ImportProgress::class)->checkpoint($run);
                    $entry = $zip->statIndex($i); ImportPath::entry($entry['name']);
                    $zip->getExternalAttributesIndex($i, $os, $attributes);
                    if ($os === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('Archive links are not supported.');
                    $this->limit($bytes, $count, (int) $entry['size'],false);
                    $destination=$root.'/'.$entry['name'];$signature=['size'=>$entry['size'],'crc'=>$entry['crc']];
                    if(!app(ImportJournal::class)->done($run,'extract:'.$destination,$signature) || !is_file($destination) || filesize($destination)!==$entry['size'])$pending+=$entry['size'];
                }
                $free=disk_free_space($this->inboxRoot);
                if($free===false || $free-$pending<config('platform.media_upload_reserve_free_bytes'))throw new RuntimeException('Not enough storage to extract archive.');
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    app(ImportProgress::class)->checkpoint($run);
                    $entry = $zip->statIndex($i);
                    if (str_ends_with($entry['name'], '/')) continue;
                    $key='extract:'.$root.'/'.$entry['name']; $signature=['size'=>$entry['size'],'crc'=>$entry['crc']];
                    if (app(ImportJournal::class)->done($run,$key,$signature) && is_file($root.'/'.$entry['name']) && filesize($root.'/'.$entry['name'])===$entry['size']) continue;
                    $stream = $zip->getStream($entry['name']);
                    if (! $stream) throw new RuntimeException('Cannot read archive entry.');
                    try { $this->write($stream, $root, $entry['name'], (int) $entry['size'],$run); } finally { fclose($stream); }
                    app(ImportJournal::class)->record($run,$key,$entry['name'],'archive-entry','complete',null,['signature'=>$signature]);
                }
            } finally { $zip->close(); }
            return;
        }
        if (! preg_match('/\.(tar|tar\.gz|tgz)$/i', $name)) throw new RuntimeException('Supported archives: ZIP, TAR, TAR.GZ, TGZ.');
        $suffix = preg_match('/\.(tgz|gz)$/i', $name) ? '.tar.gz' : '.tar';
        $alias = dirname($root).'/container'.$suffix;
        if (! is_file($alias) && ! copy($archive, $alias)) throw new RuntimeException('Cannot open TAR archive.');
        $tar = new \PharData($alias);
        $iterator = new \RecursiveIteratorIterator($tar);
        foreach ($iterator as $file) {
            app(ImportProgress::class)->checkpoint($run);
            $entry = substr($file->getPathname(), strlen('phar://'.str_replace('\\', '/', $alias).'/'));
            ImportPath::entry($entry);
            if ($file->isLink()) throw new RuntimeException('Archive links are not supported.');
            $this->limit($bytes, $count, $file->getSize());
        }
        foreach ($iterator as $file) {
            app(ImportProgress::class)->checkpoint($run);
            if (! $file->isFile()) continue;
            $entry = substr($file->getPathname(), strlen('phar://'.str_replace('\\', '/', $alias).'/'));
            $stream = fopen($file->getPathname(), 'rb');
            if (! $stream) throw new RuntimeException('Cannot read archive entry.');
            $key='extract:'.$root.'/'.$entry; $signature=['size'=>$file->getSize()];
            try {
                if (! app(ImportJournal::class)->done($run,$key,$signature) || ! is_file($root.'/'.$entry)) {
                    $this->write($stream,$root,$entry,$file->getSize(),$run);
                    app(ImportJournal::class)->record($run,$key,$entry,'archive-entry','complete',null,['signature'=>$signature]);
                }
            } finally { fclose($stream); }
        }
    }
    private function limit(int &$bytes, int &$count, int $size,bool $checkStorage=true): void
    {
        $bytes += $size; $count++;
        if ($count > 100000 || $bytes > config('platform.media_upload_max_archive_bytes')) throw new RuntimeException('Archive extraction limit exceeded.');
        if(!$checkStorage)return;
        $free = disk_free_space($this->inboxRoot);
        if ($free === false || $free - $bytes < config('platform.media_upload_reserve_free_bytes')) throw new RuntimeException('Not enough storage to extract archive.');
    }
    private function write($stream, string $root, string $entry, int $size,ImportRun $run): void
    {
        $destination = $root.'/'.ImportPath::entry($entry);
        if (! is_dir(dirname($destination))) mkdir(dirname($destination), 0700, true);
        ImportPath::resolve($root, dirname($destination));
        if (is_link($destination) || is_link($destination.'.part')) throw new RuntimeException('Unsafe destination.');
        $output = fopen($destination.'.part', 'wb');
        if (! $output) throw new RuntimeException('Storage unavailable.');
        $written=0;
        try {
            while (! feof($stream)) {
                app(ImportProgress::class)->checkpoint($run);
                $chunk=fread($stream,8*1024*1024);
                if ($chunk===false) throw new RuntimeException('Cannot read archive entry.');
                $length=strlen($chunk); $offset=0;
                while($offset<$length) { $n=fwrite($output,substr($chunk,$offset)); if(!$n) throw new RuntimeException('Storage unavailable.'); $offset+=$n; }
                $written+=$length;
                if($written>$size) throw new RuntimeException('Archive entry exceeds its declared size.');
            }
        } finally { fclose($output); }
        if ($written !== $size || ! rename($destination.'.part', $destination)) throw new RuntimeException('Archive entry is incomplete.');
    }
}

<?php
declare(strict_types=1);

namespace Atapin\Intake;

use PDO;
use RuntimeException;

final class IntakeError extends RuntimeException
{
    public function __construct(public readonly string $key, public readonly int $status = 400)
    {
        parent::__construct($key);
    }
}

/** Original bytes live outside the document root; SQLite is only the intake catalogue. */
final class Archive
{
    public readonly PDO $db;
    public readonly string $root;
    public const CHUNK_SIZE = 4 * 1024 * 1024;

    public function __construct(public readonly array $config)
    {
        umask(0077);
        $root = realpath($config['storage_path']);
        if ($root === false || !is_writable($root)) {
            throw new IntakeError('storage_unavailable', 503);
        }
        foreach ([realpath(__DIR__ . '/../public'), realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../public')] as $public) {
            if ($public && self::inside($root, $public)) {
                throw new IntakeError('unsafe_storage', 503);
            }
        }
        $this->root = $root;
        self::directory($root . '/.staging');
        $this->db = new PDO('sqlite:' . $root . '/catalogue.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA busy_timeout = 10000; PRAGMA foreign_keys = ON;');
    }

    public static function inside(string $path, string $parent): bool
    {
        $path = str_replace('\\', '/', $path);
        $parent = rtrim(str_replace('\\', '/', $parent), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $parent = strtolower($parent);
        }
        return $path === $parent || str_starts_with($path, $parent . '/');
    }

    public static function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new IntakeError('storage_unavailable', 503);
        }
    }

    public function migrate(): void
    {
        $this->db->exec('PRAGMA journal_mode = WAL; PRAGMA synchronous = FULL;
            CREATE TABLE IF NOT EXISTS uploads (
                id TEXT PRIMARY KEY, request_key TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
                relative_path TEXT NOT NULL, size INTEGER NOT NULL, modified INTEGER NOT NULL,
                offset INTEGER NOT NULL DEFAULT 0, state TEXT NOT NULL DEFAULT \'uploading\',
                category TEXT, mime TEXT, stored_path TEXT, created_at TEXT NOT NULL, completed_at TEXT
            );
            CREATE INDEX IF NOT EXISTS uploads_state ON uploads(state, created_at);
            CREATE TABLE IF NOT EXISTS chunks (
                upload_id TEXT NOT NULL REFERENCES uploads(id), start INTEGER NOT NULL,
                bytes INTEGER NOT NULL, sha256 TEXT NOT NULL, PRIMARY KEY(upload_id, start)
            );');
    }

    public function start(array $data): array
    {
        $key = $data['request_key'] ?? '';
        $name = $data['name'] ?? '';
        $size = $data['size'] ?? null;
        $relative = $data['relative_path'] ?? '';
        $modified = $data['modified'] ?? 0;
        if (!is_string($key) || !preg_match('/^[a-f0-9-]{36}$/D', $key)
            || !is_string($name) || $name === '' || strlen($name) > 1000 || preg_match('/[\x00-\x1f\x7f]/', $name)
            || !is_string($relative) || strlen($relative) > 4000 || preg_match('/[\x00-\x1f\x7f]/', $relative)
            || !is_int($size) || $size < 0 || !is_int($modified) || $modified < 0) {
            throw new IntakeError('invalid_file');
        }
        if ($size > $this->config['max_file_bytes']) {
            throw new IntakeError('file_too_large', 413);
        }
        // User paths are metadata only. They never choose a directory on the server.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->db->prepare('SELECT * FROM uploads WHERE request_key = ?');
            $query->execute([$key]);
            if ($existing = $query->fetch(PDO::FETCH_ASSOC)) {
                if ($existing['name'] !== $name || (int)$existing['size'] !== $size || (int)$existing['modified'] !== $modified || $existing['relative_path'] !== $relative) {
                    throw new IntakeError('request_conflict', 409);
                }
                $this->db->exec('COMMIT');
                return $this->publicRow($existing);
            }
            $stats = $this->db->query("SELECT COALESCE(SUM(size),0) AS reserved, SUM(CASE WHEN state != 'complete' THEN size-offset ELSE 0 END) AS pending, SUM(CASE WHEN state != 'complete' THEN 1 ELSE 0 END) AS active FROM uploads")->fetch(PDO::FETCH_ASSOC);
            if ((int)$stats['active'] >= 10000) {
                throw new IntakeError('too_many_pending', 429);
            }
            $available = disk_free_space($this->root);
            if ($available === false || $available - (int)$stats['pending'] - $size < $this->config['reserve_free_bytes']
                || (int)$stats['reserved'] + $size > $this->config['max_archive_bytes']) {
                throw new IntakeError('storage_full', 507);
            }
            $id = bin2hex(random_bytes(16));
            $query = $this->db->prepare('INSERT INTO uploads (id,request_key,name,relative_path,size,modified,created_at) VALUES (?,?,?,?,?,?,?)');
            $query->execute([$id, $key, $name, $relative, $size, $modified, gmdate('c')]);
            $this->db->exec('COMMIT');
            return $this->publicRow($this->row($id));
        } catch (\Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    public function row(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new IntakeError('not_found', 404);
        }
        $query = $this->db->prepare('SELECT * FROM uploads WHERE id = ?');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new IntakeError('not_found', 404);
        }
        return $row;
    }

    private function lock(string $id)
    {
        $this->row($id);
        $lock = fopen($this->root . '/.staging/' . $id . '.lock', 'c+b');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            throw new IntakeError('upload_busy', 409);
        }
        return $lock;
    }

    public function append(string $id, int $offset, string $body, string $sha256): array
    {
        if ($offset < 0 || strlen($body) === 0 || strlen($body) > self::CHUNK_SIZE) {
            throw new IntakeError('invalid_chunk', 413);
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $sha256) || !hash_equals(hash('sha256', $body), $sha256)) {
            throw new IntakeError('checksum_mismatch', 422);
        }
        $lock = $this->lock($id);
        $file = null;
        try {
            $row = $this->row($id);
            if ($row['state'] !== 'uploading' || $offset !== (int)$row['offset']) {
                // A lost response must not append the same chunk a second time.
                return $this->publicRow($row);
            }
            $length = strlen($body);
            if ($offset + $length > (int)$row['size']) {
                throw new IntakeError('invalid_chunk');
            }
            $free = disk_free_space($this->root);
            if ($free === false || $free - $length < $this->config['reserve_free_bytes']) {
                throw new IntakeError('storage_full', 507);
            }
            $file = fopen($this->root . '/.staging/' . $id . '.part', 'c+b');
            if (!$file) throw new IntakeError('storage_unavailable', 503);
            $actual = fstat($file)['size'];
            if ($actual < $offset) throw new IntakeError('archive_inconsistent', 500);
            // Recover bytes written just before a process failure / failed DB commit.
            if (!ftruncate($file, $offset) || fseek($file, $offset) !== 0) throw new IntakeError('storage_unavailable', 503);
            $written = 0;
            while ($written < $length) {
                $count = fwrite($file, substr($body, $written));
                if (!$count) throw new IntakeError('storage_full', 507);
                $written += $count;
            }
            if (!fflush($file) || !fsync($file)) throw new IntakeError('storage_unavailable', 503);
            $this->db->beginTransaction();
            try {
                $query = $this->db->prepare('INSERT INTO chunks (upload_id,start,bytes,sha256) VALUES (?,?,?,?)');
                $query->execute([$id, $offset, $length, $sha256]);
                $query = $this->db->prepare('UPDATE uploads SET offset = ? WHERE id = ?');
                $query->execute([$offset + $length, $id]);
                $this->db->commit();
            } catch (\Throwable $error) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $error;
            }
            return $this->publicRow($this->row($id));
        } finally {
            if (is_resource($file)) fclose($file);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function category(string $name, string $mime): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        // Container formats often have generic MIME types; preserve their intended family.
        $groups = [
            'images' => ['jpg','jpeg','png','gif','webp','avif','heic','heif','tif','tiff','bmp','svg','raw','cr2','cr3','nef','arw','dng','psd','ai','eps'],
            'video' => ['mp4','mov','m4v','mkv','avi','webm','mts','m2ts','mpg','mpeg','wmv','flv','3gp','vob','mxf'],
            'audio' => ['mp3','wav','m4a','aac','flac','ogg','oga','opus','aiff','aif','wma','amr','mid','midi'],
            'documents' => ['pdf','doc','docx','odt','rtf','xls','xlsx','ods','ppt','pptx','odp','epub','pages','numbers','key'],
            'text' => ['txt','md','markdown','csv','tsv','json','xml','html','htm','srt','vtt','tex','log'],
            'archives' => ['zip','7z','rar','tar','gz','bz2','xz','tgz'],
        ];
        foreach ($groups as $category => $extensions) {
            if (in_array($extension, $extensions, true)) return $category;
        }
        foreach (['image/' => 'images', 'video/' => 'video', 'audio/' => 'audio', 'text/' => 'text'] as $prefix => $category) {
            if (str_starts_with($mime, $prefix)) return $category;
        }
        return $mime === 'application/pdf' ? 'documents' : 'other';
    }

    public function finish(string $id): array
    {
        $lock = $this->lock($id);
        try {
            $row = $this->row($id);
            if ($row['state'] === 'complete') return $this->publicRow($row);
            if ((int)$row['offset'] !== (int)$row['size']) throw new IntakeError('incomplete_upload', 409);
            $source = $this->root . '/.staging/' . $id . '.part';
            if ($row['state'] === 'uploading') {
                if ((int)$row['size'] === 0 && !is_file($source)) {
                    if (file_put_contents($source, '') === false) throw new IntakeError('storage_unavailable', 503);
                }
                // Only inspect a sample; finalization never reads a multi-GB file into RAM.
                $handle = fopen($source, 'rb');
                if (!$handle) throw new IntakeError('archive_inconsistent', 500);
                $sample = fread($handle, 262144);
                fclose($handle);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($sample ?: '') ?: 'application/octet-stream';
                $category = self::category($row['name'], $mime);
                $safeName = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $row['name']) ?: 'file';
                $safeName = trim($safeName, '. ');
                // Byte-safe filesystem name; full original name remains in the catalogue.
                $safeName = preg_replace('/^(.{0,140}).*$/us', '$1', $safeName) ?: 'file';
                while (strlen($safeName) > 180) $safeName = preg_replace('/.$/us', '', $safeName);
                $path = $category . '/' . gmdate('Y/m', strtotime($row['created_at'])) . '/' . $id . '--' . ($safeName ?: 'file');
                $query = $this->db->prepare("UPDATE uploads SET state='finalizing', category=?, mime=?, stored_path=? WHERE id=?");
                $query->execute([$category, $mime, $path, $id]);
                $row = $this->row($id);
            }
            $destination = $this->root . '/' . $row['stored_path'];
            self::directory(dirname($destination));
            if (!is_file($destination)) {
                if (!is_file($source) || filesize($source) !== (int)$row['size'] || !rename($source, $destination)) {
                    throw new IntakeError('archive_inconsistent', 500);
                }
            }
            if (filesize($destination) !== (int)$row['size']) throw new IntakeError('archive_inconsistent', 500);
            $completed = gmdate('c');
            $query = $this->db->prepare('SELECT start,bytes,sha256 FROM chunks WHERE upload_id=? ORDER BY start');
            $query->execute([$id]);
            $manifest = ['schema' => 'atapin-intake/v1', 'id' => $id, 'original_name' => $row['name'],
                'original_relative_path' => $row['relative_path'], 'bytes' => (int)$row['size'],
                'source_modified_ms' => (int)$row['modified'], 'mime' => $row['mime'], 'category' => $row['category'],
                'stored_path' => $row['stored_path'], 'received_at' => $row['created_at'], 'completed_at' => $completed,
                'checksums' => ['algorithm' => 'sha256', 'scope' => 'individual_chunks', 'chunks' => $query->fetchAll(PDO::FETCH_ASSOC)]];
            self::atomicJson($destination . '.metadata.json', $manifest);
            $query = $this->db->prepare("UPDATE uploads SET state='complete', completed_at=? WHERE id=?");
            $query->execute([$completed, $id]);
            return $this->publicRow($this->row($id));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function atomicJson(string $path, array $data): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $file = fopen($temporary, 'xb');
        if (!$file) throw new IntakeError('storage_unavailable', 503);
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $written = 0;
            while ($written < strlen($json)) {
                $count = fwrite($file, substr($json, $written));
                if (!$count) throw new IntakeError('storage_full', 507);
                $written += $count;
            }
            if (!fflush($file) || !fsync($file)) throw new IntakeError('storage_unavailable', 503);
        } finally {
            fclose($file);
        }
        if (!rename($temporary, $path)) throw new IntakeError('storage_unavailable', 503);
    }

    public function publicRow(array $row): array
    {
        return array_intersect_key($row, array_flip(['id','name','size','offset','state','category','created_at','completed_at']));
    }

    public function overview(): array
    {
        $groups = $this->db->query("SELECT category,COUNT(*) AS count,SUM(size) AS bytes FROM uploads WHERE state='complete' GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
        // The open intake does not expose a list of other people's original file names.
        return ['groups' => $groups, 'chunk_size' => self::CHUNK_SIZE,
            'max_file_bytes' => $this->config['max_file_bytes']];
    }
}

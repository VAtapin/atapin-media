<?php
declare(strict_types=1);

use Atapin\Intake\Archive;

require __DIR__ . '/../src/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);

try {
    $archive = new Archive(intake_config());
    $command = $argv[1] ?? 'status';
    if ($command === 'status') {
        $stats = $archive->db->query('SELECT state,COUNT(*) AS files,SUM(size) AS bytes,SUM(offset) AS received FROM uploads GROUP BY state')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['php' => PHP_VERSION, 'storage' => $archive->root, 'free_bytes' => disk_free_space($archive->root),
            'max_file_bytes' => $archive->config['max_file_bytes'], 'quota_bytes' => $archive->config['max_archive_bytes'], 'uploads' => $stats], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } elseif ($command === 'verify') {
        // Offline/background CLI verification; never do this expensive work in a web request.
        $failed = 0; $checked = 0;
        $rows = $archive->db->query("SELECT * FROM uploads WHERE state='complete'");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $path = $archive->root . '/' . $row['stored_path'];
            $valid = is_file($path) && filesize($path) === (int)$row['size'] && is_file($path . '.metadata.json');
            if ($valid) {
                $file = fopen($path, 'rb');
                $chunks = $archive->db->prepare('SELECT * FROM chunks WHERE upload_id=? ORDER BY start');
                $chunks->execute([$row['id']]);
                $offset = 0;
                while ($chunk = $chunks->fetch(PDO::FETCH_ASSOC)) {
                    $body = '';
                    while (strlen($body) < (int)$chunk['bytes'] && !feof($file)) {
                        $part = fread($file, (int)$chunk['bytes'] - strlen($body));
                        if ($part === false || $part === '') break;
                        $body .= $part;
                    }
                    if ((int)$chunk['start'] !== $offset || strlen($body) !== (int)$chunk['bytes'] || !hash_equals($chunk['sha256'], hash('sha256', $body))) $valid = false;
                    $offset += strlen($body);
                }
                if ($offset !== (int)$row['size']) $valid = false;
                fclose($file);
            }
            $checked++;
            if (!$valid) { $failed++; echo "FAILED {$row['id']}\n"; }
        }
        echo "Checked: {$checked}; failed: {$failed}\n";
        exit($failed ? 1 : 0);
    } elseif ($command === 'backup-catalogue') {
        $destination = $argv[2] ?? '';
        if (!$destination || file_exists($destination)) throw new RuntimeException('Supply a new absolute destination filename.');
        if (!preg_match('~^(?:/|[a-zA-Z]:[\\\\/])~', $destination)) throw new RuntimeException('Use an absolute destination path.');
        $archive->db->exec('VACUUM INTO ' . $archive->db->quote($destination));
        echo "Catalogue snapshot created. Original files and metadata sidecars must also be backed up.\n";
    } else {
        throw new RuntimeException('Commands: status | verify | backup-catalogue /absolute/backup.sqlite');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

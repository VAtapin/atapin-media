<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../src/Archive.php';
use Atapin\Intake\Archive;
use Atapin\Intake\IntakeError;

$root = sys_get_temp_dir() . '/atapin-test-' . bin2hex(random_bytes(6));
Archive::directory($root);
$config = ['storage_path' => $root, 'max_file_bytes' => 10000000, 'max_archive_bytes' => 30000000, 'reserve_free_bytes' => 0];
$tests = 0;
function check(bool $condition, string $label): void {
    global $tests;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $tests++; echo "PASS: {$label}\n";
}
function fails(callable $call, string $key): void {
    try { $call(); } catch (IntakeError $error) { check($error->key === $key, 'reject ' . $key); return; }
    throw new RuntimeException('Expected ' . $key);
}
function fileData(string $name, int $size): array {
    return ['request_key' => bin2hex(random_bytes(18)), 'name' => $name, 'size' => $size, 'modified' => 100, 'relative_path' => ''];
}

try {
    $archive = new Archive($config); $archive->migrate();
    fails(fn() => $archive->start(fileData('too big.mp4', 10000001)), 'file_too_large');
    fails(fn() => $archive->start(fileData("bad\nname.txt", 0)), 'invalid_file');
    fails(fn() => $archive->row('../bad'), 'not_found');
    $data = fileData('Проповедь ü & <script>.txt', 11);
    $data['relative_path'] = '../../outside/original.txt';
    $row = $archive->start($data); $id = $row['id'];
    check($archive->start($data)['id'] === $id, 'start is idempotent after a lost response');
    $different = $data; $different['size'] = 10;
    fails(fn() => $archive->start($different), 'request_conflict');
    fails(fn() => $archive->append($id, 0, 'hello ', str_repeat('0', 64)), 'checksum_mismatch');
    check((int)$archive->row($id)['offset'] === 0, 'bad checksum leaves no accepted bytes');
    $row = $archive->append($id, 0, 'hello ', hash('sha256', 'hello '));
    check((int)$row['offset'] === 6, 'first chunk accepted');
    $row = $archive->append($id, 0, 'hello ', hash('sha256', 'hello '));
    check((int)$row['offset'] === 6, 'chunk retry does not duplicate bytes');
    fails(fn() => $archive->finish($id), 'incomplete_upload');
    // Simulate a crash after disk write but before the DB update.
    file_put_contents($root . '/.staging/' . $id . '.part', 'uncommitted garbage', FILE_APPEND);
    $reopened = new Archive($config);
    $reopened->append($id, 6, 'world', hash('sha256', 'world'));
    $row = $reopened->finish($id);
    $stored = $reopened->row($id);
    check($row['state'] === 'complete' && $row['category'] === 'text', 'resume and classify after process restart');
    check(file_get_contents($root . '/' . $stored['stored_path']) === 'hello world', 'byte-identical original after crash recovery');
    check(str_starts_with($stored['stored_path'], 'text/') && !str_contains($stored['stored_path'], '..'), 'source paths cannot escape archive');
    $metadata = json_decode(file_get_contents($root . '/' . $stored['stored_path'] . '.metadata.json'), true);
    check($metadata['original_name'] === $data['name'] && count($metadata['checksums']['chunks']) === 2, 'original metadata and chunk checksums exported');
    check($reopened->finish($id)['state'] === 'complete', 'finalize retry is idempotent');
    $empty = $archive->start(fileData('empty.txt', 0));
    check($archive->finish($empty['id'])['state'] === 'complete', 'zero-byte file accepted');
    $same = $archive->start(fileData($data['name'], 1));
    $archive->append($same['id'], 0, 'x', hash('sha256', 'x'));
    $archive->finish($same['id']);
    check($archive->row($same['id'])['stored_path'] !== $stored['stored_path'], 'duplicate filenames never overwrite');
    check(!array_key_exists('recent', $archive->overview()), 'open overview does not expose original filenames');
    check(!array_key_exists('stored_path', $row), 'public responses do not expose disk paths');
    // The second attempt recovers an interrupted finalization after the original was moved.
    $archive->db->prepare("UPDATE uploads SET state='finalizing',completed_at=NULL WHERE id=?")->execute([$id]);
    check($archive->finish($id)['state'] === 'complete', 'finalize recovers after rename before DB commit');
    foreach (['x.HEIC'=>'images','x.CR3'=>'images','x.mov'=>'video','x.m4a'=>'audio','x.docx'=>'documents','x.pdf'=>'documents','x.srt'=>'text','x.zip'=>'archives','x.php'=>'other'] as $name => $category) {
        check(Archive::category($name, 'application/octet-stream') === $category, 'classify ' . $name);
    }
    $quota = new Archive([...$config, 'max_archive_bytes' => 20]);
    fails(fn() => $quota->start(fileData('reserved.mp4', 20)), 'storage_full');
    $overrun = $archive->start(fileData('small.bin', 1));
    fails(fn() => $archive->append($overrun['id'], 0, 'xx', hash('sha256', 'xx')), 'invalid_chunk');
    $lock = fopen($root . '/.staging/' . $overrun['id'] . '.lock', 'c+b'); flock($lock, LOCK_EX);
    fails(fn() => $archive->append($overrun['id'], 0, 'x', hash('sha256', 'x')), 'upload_busy');
    flock($lock, LOCK_UN); fclose($lock);
    $_SERVER['DOCUMENT_ROOT'] = $root;
    fails(fn() => new Archive($config), 'unsafe_storage');
    unset($_SERVER['DOCUMENT_ROOT']);
    echo "{$tests} checks passed.\n";
} finally {
    // Only remove this test's freshly generated, explicitly verified temp directory.
    $resolved = realpath($root);
    if ($resolved && Archive::inside($resolved, sys_get_temp_dir()) && str_starts_with(basename($resolved), 'atapin-test-')) {
        $archive = $reopened = $quota = null;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { if ($item->isDir()) rmdir($item->getPathname()); else @unlink($item->getPathname()); }
        @rmdir($resolved);
    }
}

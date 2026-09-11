<?php
declare(strict_types=1);

use Atapin\Intake\Archive;

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') exit(1);
try {
    if (PHP_VERSION_ID < 80200 || PHP_INT_SIZE < 8) throw new RuntimeException('PHP 8.2+ (64-bit) is required.');
    foreach (['pdo_sqlite', 'fileinfo'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('Missing PHP extension: ' . $extension);
    }
    $args = getopt('', ['storage:', 'origin:', 'max-file-gb:', 'quota-gb:']);
    $storage = $args['storage'] ?? '';
    $origin = rtrim($args['origin'] ?? '', '/');
    $parts = parse_url($origin);
    $local = getenv('INTAKE_ALLOW_LOCAL_HTTP') === '1';
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
        || (($parts['scheme'] ?? '') !== 'https' && !($local && ($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['127.0.0.1', 'localhost'], true)))) {
        throw new RuntimeException('Use --origin=https://upload.example.com (origin only; no path).');
    }
    if (!is_string($storage) || !preg_match('~^(?:/|[a-zA-Z]:[\\\\/])~', $storage)) throw new RuntimeException('Use --storage=/absolute/private/path');
    $target = getenv('INTAKE_CONFIG') ?: __DIR__ . '/../config.local.php';
    if (file_exists($target)) throw new RuntimeException('Configuration already exists; nothing changed. Run intake/bin/console.php status.');
    $max = filter_var($args['max-file-gb'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    $quota = filter_var($args['quota-gb'] ?? 500, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($max === false || $quota === false || $max > $quota) throw new RuntimeException('Use integer GB limits with max-file-gb <= quota-gb.');
    umask(0077);
    Archive::directory($storage);
    $config = ['origin' => $origin, 'storage_path' => realpath($storage), 'max_file_bytes' => $max * 1024 ** 3,
        'max_archive_bytes' => $quota * 1024 ** 3, 'reserve_free_bytes' => 2 * 1024 ** 3];
    $archive = new Archive($config);
    $archive->migrate();
    $contents = "<?php\ndeclare(strict_types=1);\n// Local server settings. Never commit this file.\nreturn " . var_export($config, true) . ";\n";
    $handle = fopen($target, 'xb');
    if (!$handle) throw new RuntimeException('Cannot create configuration file.');
    $written = fwrite($handle, $contents);
    fflush($handle); fsync($handle); fclose($handle);
    if ($written !== strlen($contents)) throw new RuntimeException('Could not save the complete configuration.');
    echo "Intake ready.\nURL: {$origin}/\nDocument root: " . realpath(__DIR__ . '/../public') . "\nArchive: {$config['storage_path']}\nMaximum file: {$max} GiB; archive quota: {$quota} GiB.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

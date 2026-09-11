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
    $args = getopt('', ['storage:', 'origin:', 'max-file-gb:', 'quota-gb:', 'base-path:', 'update-origin', 'password-stdin']);
    $storage = $args['storage'] ?? '';
    $origin = rtrim($args['origin'] ?? '', '/');
    $parts = parse_url($origin);
    $local = getenv('INTAKE_ALLOW_LOCAL_HTTP') === '1';
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
        || (($parts['scheme'] ?? '') !== 'https' && !($local && ($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['127.0.0.1', 'localhost'], true)))) {
        throw new RuntimeException('Use --origin=https://example.com (origin only; no path).');
    }
    $basePath = $args['base-path'] ?? '/';
    if (!is_string($basePath) || !preg_match('~^/(?:[a-zA-Z0-9_-]+/)*$~D', $basePath)) throw new RuntimeException('Use --base-path=/upload/');
    $target = intake_config_path();
    $existing = is_file($target);
    if ($existing && !array_key_exists('update-origin', $args)) throw new RuntimeException('Configuration already exists; nothing changed. Run intake/bin/console.php status.');
    $max = filter_var($args['max-file-gb'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    $quota = filter_var($args['quota-gb'] ?? 500, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($max === false || $quota === false || $max > $quota) throw new RuntimeException('Use integer GB limits with max-file-gb <= quota-gb.');
    umask(0077);
    if ($existing) {
        $config = intake_config();
        // Switching the public URL must never redirect, empty or reset an existing archive.
        $config['origin'] = $origin;
    } else {
        if (!is_string($storage) || !preg_match('~^(?:/|[a-zA-Z]:[\\\\/])~', $storage)) throw new RuntimeException('Use --storage=/absolute/private/path');
        Archive::directory($storage);
        $config = ['origin' => $origin, 'storage_path' => realpath($storage), 'max_file_bytes' => $max * 1024 ** 3,
            'max_archive_bytes' => $quota * 1024 ** 3, 'reserve_free_bytes' => 2 * 1024 ** 3];
    }
    $generatedPassword = null;
    if (array_key_exists('password-stdin', $args)) {
        $password = rtrim((string)fgets(STDIN, 258), "\r\n");
        if ($password === '' || strlen($password) > 72) throw new RuntimeException('Enter a non-empty password (up to 72 bytes).');
        $config['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        unset($password);
    } elseif (empty($config['password_hash'])) {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $generatedPassword = '';
        for ($i = 0; $i < 6; $i++) $generatedPassword .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $config['password_hash'] = password_hash($generatedPassword, PASSWORD_DEFAULT);
    }
    $archive = new Archive($config);
    if (!$existing) $archive->migrate();
    $contents = "<?php\ndeclare(strict_types=1);\n// Local server settings. Never commit this file.\nreturn " . var_export($config, true) . ";\n";
    $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $handle = fopen($temporary, 'xb');
    if (!$handle) throw new RuntimeException('Cannot create configuration file.');
    $written = fwrite($handle, $contents);
    $synced = fflush($handle) && fsync($handle); fclose($handle);
    if ($written !== strlen($contents)) throw new RuntimeException('Could not save the complete configuration.');
    if (!$synced || !rename($temporary, $target)) throw new RuntimeException('Could not save configuration.');
    $max = $config['max_file_bytes'] / 1024 ** 3;
    $quota = $config['max_archive_bytes'] / 1024 ** 3;
    echo "Intake ready.\nURL: {$origin}{$basePath}\nArchive: {$config['storage_path']}\nMaximum file: {$max} GiB; archive quota: {$quota} GiB.\n";
    if ($generatedPassword !== null) echo "Password: {$generatedPassword}\nSave this password; it is shown only once.\n";
    if (array_key_exists('password-stdin', $args)) echo "Password updated. Previous browser logins are now invalid.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

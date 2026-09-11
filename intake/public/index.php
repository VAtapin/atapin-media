<?php
declare(strict_types=1);
// Also reject PHP's development-server PATH_INFO fallback for nonexistent paths.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/') . '/';
if ($basePath !== '/' && $requestPath === rtrim($basePath, '/')) {
    header('Location: ' . $basePath, true, 308);
    exit;
}
if (!in_array($requestPath, [$basePath, $basePath . 'index.php'], true)) {
    http_response_code(404);
    exit;
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
$translations = require __DIR__ . '/../lang/de.php';
$t = static fn(string $key): string => htmlspecialchars($translations[$key] ?? $key, ENT_QUOTES, 'UTF-8');
$asset = static fn(string $file): string => htmlspecialchars((defined('INTAKE_ASSET_BASE') ? INTAKE_ASSET_BASE : '') . $file, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f285b">
  <meta name="description" content="<?= $t('description') ?>">
  <title><?= $t('title') ?></title>
  <link rel="icon" href="<?= $asset('favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $asset('fonts/fonts.css') ?>">
  <link rel="stylesheet" href="<?= $asset('app.css') ?>">
  <script src="<?= $asset('app.js') ?>" type="module"></script>
</head>
<body>
  <header class="header">
    <a class="brand" href="./" aria-label="<?= $t('brand_label') ?>"><span class="brand-line"><?= $t('brand_top') ?></span><span class="brand-sub"><?= $t('brand_bottom') ?></span><span class="brand-motto"><?= $t('motto') ?></span></a>
    <span class="header-label"><?= $t('archive_label') ?></span>
    <span id="connection" class="connection"><?= $t('connecting') ?></span>
  </header>
  <main>
    <div class="page-heading"><div><p class="eyebrow"><?= $t('eyebrow') ?></p><h1><?= $t('heading') ?></h1><p class="intro"><?= $t('intro') ?></p></div><span class="private-note"><?= $t('originals') ?></span></div>
    <div id="notice" class="notice" role="status" hidden></div>
    <div id="workspace" hidden>
      <section id="dropzone" class="dropzone" aria-labelledby="upload-title">
        <div class="upload-icon" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M16 22V5m-6 6 6-6 6 6M6 21v6h20v-6"/></svg></div>
        <h2 id="upload-title"><?= $t('upload_heading') ?></h2>
        <p><?= $t('upload_intro') ?></p>
        <div class="pick-actions"><label class="button primary" for="files"><?= $t('choose_files') ?> <span aria-hidden="true">＋</span></label><input class="file-input" id="files" type="file" multiple><label class="button secondary" id="folder-label" for="folder"><?= $t('choose_folder') ?></label><input class="file-input" id="folder" type="file" multiple webkitdirectory></div>
        <p class="upload-hint"><?= $t('formats') ?> <span id="limit"></span></p>
      </section>
      <section class="queue-section" id="queue-section" hidden aria-labelledby="queue-title">
        <div class="section-heading"><div><h2 id="queue-title"><?= $t('uploads') ?></h2><p id="queue-summary" role="status" aria-live="polite"></p></div><button class="button secondary small" id="pause" type="button"><?= $t('pause') ?></button></div>
        <p class="mobile-hint"><?= $t('mobile_hint') ?></p>
        <ul id="queue" class="queue"></ul>
      </section>
      <section class="archive-section" aria-labelledby="archive-title">
        <div class="section-heading"><div><p class="eyebrow"><?= $t('sorted') ?></p><h2 id="archive-title"><?= $t('in_archive') ?></h2></div><span class="archive-total" id="archive-total"><?= $t('empty_archive') ?></span></div>
        <div id="categories" class="categories"></div>
        <div class="recent-card"><div class="recent-heading"><h3><?= $t('recent') ?></h3><button class="text-button" id="refresh" type="button"><?= $t('refresh') ?></button></div><p id="empty" class="empty"><?= $t('empty_recent') ?></p><ul id="recent" class="recent"></ul></div>
      </section>
    </div>
    <noscript><p class="notice"><?= $t('javascript') ?></p></noscript>
  </main>
  <footer><span><?= $t('brand') ?></span><span><?= $t('motto') ?></span><span><?= $t('footer') ?></span></footer>
</body>
</html>

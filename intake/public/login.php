<?php
declare(strict_types=1);
// Rendered by index.php only; direct requests must not execute a partial template.
if (!isset($t, $asset, $loginError)) { http_response_code(404); exit; }
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f285b">
  <title><?= $t('login_title') ?></title>
  <link rel="icon" href="<?= $asset('favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $asset('fonts/fonts.css') ?>">
  <link rel="stylesheet" href="<?= $asset('app.css') ?>">
</head>
<body>
  <header class="header"><a class="brand" href="./"><span class="brand-line"><?= $t('brand_top') ?></span><span class="brand-sub"><?= $t('brand_bottom') ?></span><span class="brand-motto"><?= $t('motto') ?></span></a><span class="header-label"><?= $t('archive_label') ?></span></header>
  <main class="login-main">
    <section class="login-card" aria-labelledby="login-title">
      <p class="eyebrow"><?= $t('archive_label') ?></p>
      <h1 id="login-title"><?= $t('login_heading') ?></h1>
      <p class="intro"><?= $t('login_intro') ?></p>
      <?php if ($loginError !== ''): ?><p class="notice" role="alert"><?= $t($loginError) ?></p><?php endif; ?>
      <?php if ($loginError !== 'login_unavailable'): ?>
      <form method="post" action="">
        <input type="hidden" name="action" value="login">
        <label for="password"><?= $t('password') ?></label>
        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="72" autofocus>
        <button class="button primary" type="submit"><?= $t('login_button') ?></button>
      </form>
      <p class="upload-hint"><?= $t('login_remember') ?></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>

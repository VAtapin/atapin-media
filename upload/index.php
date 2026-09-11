<?php
declare(strict_types=1);

// Public entry point when the Git checkout is the site's httpdocs directory.
define('INTAKE_ASSET_BASE', '../intake/public/');
require __DIR__ . '/../intake/public/index.php';

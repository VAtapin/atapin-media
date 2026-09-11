<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';

function checkAuth(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
$config = ['password_hash' => password_hash('test-only-password', PASSWORD_DEFAULT)];
checkAuth(!intake_authenticated($config), 'Missing cookie is rejected');
$_COOKIE[INTAKE_COOKIE] = intake_token($config, time() + 3600);
checkAuth(intake_authenticated($config), 'Valid signed cookie is accepted');
$changed = ['password_hash' => password_hash('different-test-password', PASSWORD_DEFAULT)];
checkAuth(!intake_authenticated($changed), 'Changing password revokes existing cookie');
$_COOKIE[INTAKE_COOKIE] = intake_token($config, time() - 1);
checkAuth(!intake_authenticated($config), 'Expired signed cookie is rejected');
$_COOKIE[INTAKE_COOKIE] = intake_token($config, time() + 31 * 86400);
checkAuth(!intake_authenticated($config), 'Overlong signed cookie is rejected');
$_COOKIE[INTAKE_COOKIE] = ['invalid'];
checkAuth(!intake_authenticated($config), 'Array-shaped cookie is rejected');
try {
    intake_authenticated([]);
    throw new RuntimeException('Old config without password must not allow public access');
} catch (\Atapin\Intake\IntakeError $error) {
    checkAuth($error->status === 503, 'Missing password fails closed');
}
echo "Authentication checks passed: signatures, expiry, rotation and missing configuration.\n";

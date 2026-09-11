<?php
// CLI-only setup: secrets are prompted in the terminal and never put in Git.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$target = dirname(__DIR__,3).'/private/atapin-platform/.env';
if (is_file($target)) { echo "Configuration already exists; preserving it.\n"; exit; }
$ask = function (string $label, string $default = '', bool $secret = false): string {
    fwrite(STDOUT, $label.($default !== '' ? " [$default]" : '').': ');
    if ($secret) system('stty -echo');
    try { $line = fgets(STDIN); } finally { if ($secret) { system('stty echo'); fwrite(STDOUT,"\n"); } }
    if ($line === false) throw new RuntimeException('Interactive terminal input is required.');
    $value = $secret ? rtrim($line,"\r\n") : trim($line);
    if (str_contains($value,"\0")) throw new RuntimeException('Invalid input.');
    return $value !== '' ? $value : $default;
};
$host=$ask('SQL host','127.0.0.1'); $port=$ask('SQL port','3306');
$database=$ask('Database name'); $user=$ask('Database user'); $password=$ask('Database password','',true);
if (!ctype_digit($port) || !preg_match('/^[a-zA-Z0-9_.-]+$/D',$host) || !preg_match('/^[a-zA-Z0-9_-]+$/D',$database)) throw new RuntimeException('Invalid SQL host, port or database name.');
try { new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
catch (Throwable) { fwrite(STDERR,"Cannot connect to this database. Configuration was not saved.\n"); exit(1); }
$url=$ask('Website URL','https://mannavomhimmel.de');
if (!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException('Use the public HTTPS URL.');
$values=['APP_KEY'=>'base64:'.base64_encode(random_bytes(32)),'APP_URL'=>$url,'DB_HOST'=>$host,'DB_PORT'=>$port,'DB_DATABASE'=>$database,'DB_USERNAME'=>$user,'DB_PASSWORD'=>$password];
$content=file_get_contents(dirname(__DIR__).'/.env.example');
foreach ($values as $key=>$value) {
    // Quoted dotenv values preserve spaces, #, quotes and literal ${...} passwords.
    $quoted='"'.str_replace(['\\','"','$'],['\\\\','\\"','\\$'],$value).'"';
    $content=preg_replace_callback('/^'.preg_quote($key,'/').'=.*$/m',fn()=>$key.'='.$quoted,$content);
}
umask(0077); $file=fopen($target,'x');
if (!$file) throw new RuntimeException('Cannot create private configuration.');
fwrite($file,$content); fclose($file); echo "Private configuration created.\n";

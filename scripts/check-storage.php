<?php

use HideInMatrix\TelegramUpload\Diagnostics\StorageCheck;

require __DIR__.'/../src/Diagnostics/StorageCheck.php';

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    fwrite(STDERR, "Run this read-only check as the PHP worker user (for example www-data). Root bypasses filesystem permissions.\n");
    exit(2);
}

$root = $argv[1] ?? getcwd();
if (!is_string($root) || !is_file($root.'/flarum') || !is_file($root.'/composer.json')) {
    fwrite(STDERR, "Usage: php scripts/check-storage.php /path/to/flarum\n");
    exit(2);
}

$failed = false;
foreach ((new StorageCheck())->inspect($root) as $path => $result) {
    $failed = $failed || !$result['ok'];
    $status = !$result['ok'] ? 'BLOCKED' : ($result['exists'] ? 'OK' : 'OK (can be created)');
    echo $status.' '.$path.PHP_EOL;
}
if ($failed) {
    fwrite(STDERR, "Fix the reported directory ownership/permissions as the server administrator. See docs/troubleshooting.md.\n");
}
exit($failed ? 1 : 0);

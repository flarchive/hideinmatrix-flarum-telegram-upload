<?php

$paths = [dirname(__DIR__).'/extend.php'];
foreach (['src', 'tests', 'config', 'scripts'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$directory));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && !str_contains($file->getPathname(), '/tmp/')) {
            $paths[] = $file->getPathname();
        }
    }
}
foreach ($paths as $path) {
    passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $status);
    if ($status !== 0) {
        exit($status);
    }
}

<?php

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__.'/..', FilesystemIterator::SKIP_DOTS)
);
$failed = false;
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
        continue;
    }
    exec('php -l '.escapeshellarg($path), $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output).PHP_EOL);
        $failed = true;
    }
    $output = [];
}
exit($failed ? 1 : 0);

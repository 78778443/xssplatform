<?php
/**
 * Plain-PHP test runner.
 *
 * Usage: php tests/run.php [dir ...]
 * Default dirs: tests/unit tests/integration
 * Each *_test.php file runs in its own PHP process (fresh globals, fresh bootstrap).
 */

$root = dirname(__DIR__);
chdir($root);

$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['tests/unit', 'tests/integration'];
}

$failedFiles = 0;
$ranFiles = 0;

foreach ($dirs as $dir) {
    $path = $root . '/' . ltrim($dir, '/');
    if (!is_dir($path)) {
        echo "SKIP  {$dir} (missing)\n";
        continue;
    }
    $files = glob($path . '/*_test.php') ?: [];
    sort($files);
    if ($files === []) {
        echo "SKIP  {$dir} (no *_test.php)\n";
        continue;
    }
    foreach ($files as $file) {
        $ranFiles++;
        $relative = str_replace($root . '/', '', $file);
        echo "\n=== {$relative} ===\n";
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        echo implode("\n", $output), "\n";
        if ($exit !== 0) {
            $failedFiles++;
            echo "FILE FAILED: {$relative} (exit {$exit})\n";
        }
    }
}

echo "\n{$ranFiles} test file(s) run, {$failedFiles} failed\n";
exit($failedFiles > 0 ? 1 : 0);

<?php

// Temporary deployment diagnostic. DELETE after troubleshooting.
if (($_GET['key'] ?? '') !== 'psc-x9k2mQ7vT4') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain');

echo "PHP version: " . PHP_VERSION . "\n\n";

$required = ['pdo_mysql', 'mbstring', 'openssl', 'ctype', 'xml', 'dom', 'curl', 'fileinfo', 'tokenizer', 'session', 'json'];
echo "Extensions:\n";
foreach ($required as $ext) {
    echo sprintf("  %-12s %s\n", $ext, extension_loaded($ext) ? 'OK' : 'MISSING');
}

$base = dirname(__DIR__);
echo "\nPaths:\n";
foreach ([
    'vendor/autoload.php'       => is_file($base.'/vendor/autoload.php'),
    'bootstrap/app.php'         => is_file($base.'/bootstrap/app.php'),
    '.env'                      => is_file($base.'/.env'),
    'bootstrap/cache (dir)'     => is_dir($base.'/bootstrap/cache'),
    'storage/framework/views'   => is_dir($base.'/storage/framework/views'),
    'storage/framework/cache'   => is_dir($base.'/storage/framework/cache'),
    'storage/framework/sessions'=> is_dir($base.'/storage/framework/sessions'),
    'storage/logs'              => is_dir($base.'/storage/logs'),
] as $label => $ok) {
    echo sprintf("  %-28s %s\n", $label, $ok ? 'EXISTS' : 'MISSING');
}

echo "\nWritable:\n";
foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs'] as $dir) {
    echo sprintf("  %-28s %s\n", $dir, is_writable($base.'/'.$dir) ? 'YES' : 'NO');
}

$log = $base.'/storage/logs/laravel.log';
echo "\nlaravel.log: ";
if (is_file($log)) {
    $lines = file($log);
    echo count($lines) . " lines, last 40:\n" . implode('', array_slice($lines, -40));
} else {
    echo "not found\n";
}

echo "\nBootstrap test:\n";
try {
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    echo "  app created OK\n";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "  kernel bootstrapped OK\n";
    echo "  DB: ";
    try {
        Illuminate\Support\Facades\DB::select('select 1');
        echo "connected\n";
        $tables = array_map(fn ($r) => array_values((array) $r)[0], Illuminate\Support\Facades\DB::select('show tables'));
        echo "  tables (" . count($tables) . "): " . implode(', ', $tables) . "\n";
    } catch (Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
    }
} catch (Throwable $e) {
    echo "  FATAL: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

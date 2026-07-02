<?php

// Temporary web-triggered migration runner (no SSH on host). DELETE after troubleshooting.
if (($_GET['key'] ?? '') !== 'psc-x9k2mQ7vT4') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain');
set_time_limit(300);

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();

    if (($_GET['seed'] ?? '') === '1') {
        $kernel->call('db:seed', ['--force' => true]);
        echo $kernel->output();
    }
    echo "\nDONE\n";
} catch (Throwable $e) {
    echo "FAIL: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

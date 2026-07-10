<?php

// TEMPORARY migration runner — runbook 16 §6. Token-gated, DELETE from repo
// immediately after use (FTP sync removes it from the server).

$expected = 'e2b90428c42dbb1578e41cb0f1ac18e168f6c20140cfd0b1496f9c3cceb857f3';

if (! hash_equals($expected, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/plain; charset=utf-8');

$kernel->call('migrate', ['--force' => true]);
echo $kernel->output();

echo "\n--- migrate:status ---\n";
$kernel->call('migrate:status');
echo $kernel->output();

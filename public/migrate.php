<?php

// TEMPORARY migration runner — runbook 16 §6. Token-gated, DELETE from repo
// immediately after use (FTP sync removes it from the server).

$expected = 'f986a23389164519aac96f811ef85ee10ae564108d2a4e8c';

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

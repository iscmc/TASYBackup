<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require __DIR__ . '/app/models/BackupModel.php';

$table = strtoupper($argv[1] ?? '');
$limit = (int) ($argv[2] ?? 50);
$offset = (int) ($argv[3] ?? 0);

if ($table === '') {
    fwrite(STDERR, "Uso: php debug_batch_fetch.php TABELA [LIMITE]\n");
    exit(1);
}

$model = new BackupModel();
$reflection = new ReflectionClass($model);
$method = $reflection->getMethod('fetchNewRecordsBatch');
$method->setAccessible(true);

$start = microtime(true);
$batch = $method->invoke($model, $table, $model->getUltimoSync($table), $offset, $limit);
$duration = round(microtime(true) - $start, 2);

echo json_encode([
    'table' => $table,
    'offset' => $offset,
    'limit' => $limit,
    'count' => count($batch),
    'duration_seconds' => $duration
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require __DIR__ . '/app/models/BackupModel.php';

$table = strtoupper($argv[1] ?? '');
$limit = (int) ($argv[2] ?? 50);

if ($table === '') {
    fwrite(STDERR, "Uso: php debug_batch_process.php TABELA [LIMITE]\n");
    exit(1);
}

$model = new BackupModel();
$reflection = new ReflectionClass($model);

$fetchMethod = $reflection->getMethod('fetchNewRecordsBatch');
$fetchMethod->setAccessible(true);
$processMethod = $reflection->getMethod('processarDadosSimplificado');
$processMethod->setAccessible(true);

$lastSync = $model->getUltimoSync($table);
$batch = $fetchMethod->invoke($model, $table, $lastSync, 0, $limit);

$start = microtime(true);
$result = $processMethod->invoke($model, $table, $batch);
$duration = round(microtime(true) - $start, 2);

echo json_encode([
    'table' => $table,
    'limit' => $limit,
    'result' => $result,
    'duration_seconds' => $duration
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

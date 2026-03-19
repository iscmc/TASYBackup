<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require __DIR__ . '/app/config/database.php';
require __DIR__ . '/app/models/SyncModel.php';

$tables = [];

if (!empty($argv[1])) {
    $tables[] = strtoupper($argv[1]);
} else {
    $tables = DatabaseConfig::getTablesToSync();
}

$model = new SyncModel();
$results = [];

foreach ($tables as $table) {
    $start = microtime(true);

    try {
        $result = $model->forceTableSync($table);
    } catch (Throwable $e) {
        $result = [
            'table' => $table,
            'count' => 0,
            'status' => 'fatal',
            'message' => $e->getMessage()
        ];
    }

    $result['duration_seconds'] = round(microtime(true) - $start, 2);
    $results[] = $result;

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    flush();
}

exit(0);

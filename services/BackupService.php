<?php
/**
 * Serviço de sincronização contínua do TASYBackup.
 */

set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/SyncModel.php';

class BackupService
{
    private $syncModel;
    private $syncInterval;
    private $retryInterval;
    private $singleRun;
    private $tables;
    private $logFile;

    public function __construct(array $options = [])
    {
        $this->syncInterval = (int) ($options['interval'] ?? 900);
        $this->retryInterval = (int) ($options['retry_interval'] ?? 60);
        $this->singleRun = !empty($options['once']);
        $this->tables = $this->resolveTables($options['table'] ?? null);
        $this->logFile = __DIR__ . '/../logs/sync.log';
    }

    public function run()
    {
        $this->log('INFO', 'Serviço iniciado', [
            'single_run' => $this->singleRun ? 'yes' : 'no',
            'interval_seconds' => $this->syncInterval,
            'tables' => implode(',', $this->tables)
        ]);

        do {
            $this->runCycleWithRetry();

            if ($this->singleRun) {
                break;
            }

            sleep($this->syncInterval);
        } while (true);
    }

    private function runCycleWithRetry()
    {
        while (true) {
            try {
                $this->syncModel = new SyncModel();
                $this->performSyncCycle();
                return;
            } catch (Throwable $e) {
                $this->log('ERROR', 'Falha no ciclo de sincronização', [
                    'message' => $e->getMessage()
                ]);

                if ($this->singleRun) {
                    throw $e;
                }

                sleep($this->retryInterval);
            }
        }
    }

    private function performSyncCycle()
    {
        $cycleStart = microtime(true);
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->tables as $table) {
            $tableStart = microtime(true);

            try {
                $result = $this->syncModel->forceTableSync($table);
                $duration = round(microtime(true) - $tableStart, 2);

                if (($result['status'] ?? 'error') === 'success') {
                    $successCount++;
                    $details = $result['details'] ?? [];
                    $this->log('INFO', "Tabela {$table} sincronizada", [
                        'records_processed' => $result['count'] ?? 0,
                        'inserted' => $details['inserted'] ?? 0,
                        'updated' => $details['updated'] ?? 0,
                        'errors' => $details['errors'] ?? 0,
                        'duration_seconds' => $duration
                    ]);
                } else {
                    $errorCount++;
                    $this->log('ERROR', "Falha na tabela {$table}", [
                        'message' => $result['message'] ?? 'Erro não informado',
                        'duration_seconds' => $duration
                    ]);
                }
            } catch (Throwable $e) {
                $errorCount++;
                $this->log('ERROR', "Exceção na tabela {$table}", [
                    'message' => $e->getMessage()
                ]);
            }
        }

        $this->log('INFO', 'Ciclo concluído', [
            'success_tables' => $successCount,
            'error_tables' => $errorCount,
            'duration_seconds' => round(microtime(true) - $cycleStart, 2)
        ]);
    }

    private function resolveTables($table)
    {
        if (!$table) {
            return DatabaseConfig::getTablesToSync();
        }

        $table = strtoupper(trim($table));
        $configuredTables = DatabaseConfig::getTablesToSync();

        if (!in_array($table, $configuredTables, true)) {
            throw new InvalidArgumentException("Tabela não configurada para sincronização: {$table}");
        }

        return [$table];
    }

    private function log($level, $message, array $context = [])
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $level, $message);
        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND);
    }
}

function parseServiceOptions(array $argv)
{
    $options = [
        'once' => false,
        'table' => null,
        'interval' => 900,
        'retry_interval' => 60
    ];

    foreach ($argv as $arg) {
        if ($arg === '--once') {
            $options['once'] = true;
            continue;
        }

        if (strpos($arg, '--table=') === 0) {
            $options['table'] = substr($arg, 8);
            continue;
        }

        if (strpos($arg, '--interval=') === 0) {
            $options['interval'] = (int) substr($arg, 11);
            continue;
        }

        if (strpos($arg, '--retry=') === 0) {
            $options['retry_interval'] = (int) substr($arg, 8);
        }
    }

    return $options;
}

try {
    $service = new BackupService(parseServiceOptions(array_slice($argv, 1)));
    $service->run();
} catch (Throwable $e) {
    $logFile = __DIR__ . '/../logs/sync.log';
    $line = sprintf('[%s] FATAL %s', date('Y-m-d H:i:s'), $e->getMessage());
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

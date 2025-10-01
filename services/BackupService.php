<?php
/**
 * Servidor de contingência ISCMC Off frid
 *
 * Este arquivo faz parte do framework MVC Projeto Contingenciamento.
 *
 * @category Framework
 * @package  Servidor de contingência ISCMC
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT, Apache
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-07-01
 * @maindev  Sergio Figueroa
 */

require __DIR__ . '/../app/models/BackupModel.php';

class BackupService {
    private $backupModel;
    private $syncInterval = 900; // 15 minutos em segundos
    private $retryInterval = 60; // 1 minuto para reconexão
    
    public function __construct() {
        $this->initialize();
    }
    
    private function initialize() {
        try {
            $this->backupModel = new BackupModel();
            
            if ($this->isFirstRun()) {
                $this->backupModel->performInitialBackup();
            }
            
            $this->startSyncLoop();
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            sleep($this->retryInterval);
            $this->initialize(); // Tentar reconectar
        }
    }
    
    private function isFirstRun() {
        $sql = "SELECT COUNT(*) AS count FROM TASY_SYNC_CONTROL";
        $stmt = oci_parse($this->backupModel->getLocalConnection(), $sql);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return ($row['COUNT'] == 0);
    }
    
    private function startSyncLoop() {
        while (true) {
            try {
                $this->performIncrementalSync();
                sleep($this->syncInterval);
            } catch (Exception $e) {
                $this->logError($e->getMessage());
                sleep($this->retryInterval);
            }
        }
    }
    
    private function performIncrementalSync() {
        foreach (DatabaseConfig::getTablesToSync() as $table) {
            try {
                $lastSync = $this->getLastSyncTime($table);
                $newRecords = $this->backupModel->fetchNewRecords($table, $lastSync);
                
                if (!empty($newRecords)) {
                    $this->logSync($table, count($newRecords));
                    $this->backupModel->insertDataToLocal($table, $newRecords);
                    $this->backupModel->updateSyncControl($table, count($newRecords));
                }
            } catch (Exception $e) {
                $this->logError("Erro na tabela {$table}: " . $e->getMessage());
                continue;
            }
        }
    }
    
    private function getLastSyncTime($tableName) {
        return $this->backupModel->getLastSyncTime($tableName);
    }
    
    private function logError($message) {
        $logMessage = date('[Y-m-d H:i:s]') . " ERROR: " . $message . PHP_EOL;
        file_put_contents(__DIR__ . '/../logs/sync.log', $logMessage, FILE_APPEND);
    }

    /**
     * Registra uma mensagem de sincronização no log
     * 
     * @param string $table Nome da tabela sincronizada
     * @param int $count Número de registros sincronizados
     * @param string $type Tipo de sincronização (opcional)
     */
    private function logSync($table, $count, $type = 'INCREMENTAL') {
        $logDir = __DIR__ . '/../logs';
        $logFile = $logDir . '/sync.log';
        
        // Garante que o diretório de logs existe
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Formata a mensagem de log
        $message = sprintf(
            "[%s] %s sync for %s completed. %d records transferred.",
            date('Y-m-d H:i:s'),
            $type,
            $table,
            $count
        );
        
        // Adiciona ao arquivo de log
        file_put_contents(
            $logFile,
            $message . PHP_EOL,
            FILE_APPEND
        );
        
        // Adiciona também ao log de controle no banco de dados (opcional)
        try {
            $this->backupModel->logSyncToDatabase($table, $count, $type);
        } catch (Exception $e) {
            // Se falhar, apenas registra no arquivo de log
            file_put_contents(
                $logFile,
                "[WARNING] Failed to log to database: " . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
        }
    }
}

// Iniciar serviço
$service = new BackupService();
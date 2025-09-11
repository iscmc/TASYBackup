<?php
/**
 * Servidor de contingência ISCMC Off Grid
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

require_once __DIR__ . '/BackupModel.php';

class SyncModel extends BackupModel {
    public function getSyncStatus() {
        $sql = "SELECT table_name, last_sync, record_count, status 
                FROM TASY_SYNC_CONTROL 
                ORDER BY table_name";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_execute($stmt);
        
        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }
        
        return $results;
    }
    
    public function forceTableSync($tableName) {
        try {
            $this->validarTabela($tableName);
            
            $lastSync = $this->getUltimoSync($tableName);
            $newRecords = $this->fetchNewRecords($tableName, $lastSync);
            
            if (!empty($newRecords)) {
                $this->insertDataToLocal($tableName, $newRecords);
                $count = count($newRecords);
                $this->updateSyncControl($tableName, $count);
                return ['table' => $tableName, 'count' => $count, 'status' => 'success'];
            }
            
            return ['table' => $tableName, 'count' => 0, 'status' => 'no_new_records'];
        } catch (Exception $e) {
            return [
                'table' => $tableName,
                'count' => 0,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function forceFullSync() {
        $results = [];
        foreach (DatabaseConfig::getTablesToSync() as $table) {
            try {
                $results[] = $this->forceTableSync($table);
            } catch (Exception $e) {
                $results[] = [
                    'table' => $table,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        return $results;
    }
    
    public function getSystemInfo() {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM TASY_SYNC_CONTROL) as tables_configured,
                    (SELECT COUNT(*) FROM TASY_SYNC_CONTROL WHERE status = 'ACTIVE') as tables_active,
                    (SELECT MAX(last_sync) FROM TASY_SYNC_CONTROL) as last_sync_overall,
                    (SELECT SUM(record_count) FROM TASY_SYNC_CONTROL) as total_records
                FROM DUAL";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_execute($stmt);
        
        return oci_fetch_assoc($stmt);
    }

    /**
     * Obtém os logs mais recentes do sistema
     * 
     * @param int $limit Número máximo de logs a retornar (padrão: 50)
     * @return array Lista de logs ordenados do mais recente para o mais antigo
     */
    public function getRecentLogs($limit = 50) {
        // Primeiro verifica se a tabela de logs existe
        $checkTable = "SELECT COUNT(*) FROM USER_TABLES WHERE TABLE_NAME = 'TASY_SYNC_LOGS'";
        $stmt = oci_parse($this->localConn, $checkTable);
        oci_execute($stmt);
        $tableExists = oci_fetch_assoc($stmt)['COUNT(*)'] > 0;
        oci_free_statement($stmt);
        
        if (!$tableExists) {
            return [
                [
                    'LOG_TIME' => date('Y-m-d H:i:s'),
                    'LOG_TYPE' => 'ERROR',
                    'MESSAGE' => 'Tabela de logs não encontrada no banco de dados'
                ]
            ];
        }

        // Verifica as colunas existentes na tabela
        $checkColumns = "SELECT COLUMN_NAME FROM USER_TAB_COLUMNS 
                        WHERE TABLE_NAME = 'TASY_SYNC_LOGS'";
        $stmt = oci_parse($this->localConn, $checkColumns);
        oci_execute($stmt);
        
        $columns = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $columns[] = $row['COLUMN_NAME'];
        }
        oci_free_statement($stmt);
        
        // Define os nomes das colunas baseado no que existe na tabela
        $timeColumn = in_array('LOG_TIME', $columns) ? 'LOG_TIME' : 'LOG_DATE';
        $typeColumn = in_array('LOG_TYPE', $columns) ? 'LOG_TYPE' : 'TYPE';
        $messageColumn = in_array('MESSAGE', $columns) ? 'MESSAGE' : 
                        (in_array('LOG_MESSAGE', $columns) ? 'LOG_MESSAGE' : 'DETAILS');

        $sql = "SELECT 
                    TO_CHAR($timeColumn, 'DD/MM/YYYY HH24:MI:SS') AS LOG_TIME,
                    $typeColumn AS LOG_TYPE,
                    $messageColumn AS MESSAGE
                FROM TASY_SYNC_LOGS
                ORDER BY $timeColumn DESC
                FETCH FIRST :limit ROWS ONLY";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':limit', $limit);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            return [
                [
                    'LOG_TIME' => date('Y-m-d H:i:s'),
                    'LOG_TYPE' => 'ERROR',
                    'MESSAGE' => 'Erro ao buscar logs: ' . $error['message']
                ]
            ];
        }
        
        $logs = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $logs[] = [
                'LOG_TIME' => $row['LOG_TIME'],
                'LOG_TYPE' => $row['LOG_TYPE'],
                'MESSAGE' => $row['MESSAGE']
            ];
        }
        
        oci_free_statement($stmt);
        return $logs;
    }
}
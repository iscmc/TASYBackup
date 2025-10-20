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
        $sql = "SELECT table_name, to_char(last_sync,'dd/mm/yyyy hh24:mi:ss') as last_sync, record_count, status 
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
            error_log("=== INICIANDO forceTableSync PARA: {$tableName} ===");

            // Usar o método principal atualizado do BackupModel
            $result = $this->insertDataToLocal($tableName);
            
            if ($result['success']) {
                error_log("=== SINCRONIZAÇÃO CONCLUÍDA: {$result['records_processed']} registros ===");
                return [
                    'table' => $tableName, 
                    'count' => $result['records_processed'], 
                    'status' => 'success',
                    'details' => $result
                ];
            } else {
                error_log("=== SINCRONIZAÇÃO FALHOU: {$result['message']} ===");
                return [
                    'table' => $tableName,
                    'count' => 0,
                    'status' => 'error',
                    'message' => $result['message']
                ];
            }
            
        } catch (Exception $e) {
            error_log("ERRO em forceTableSync: " . $e->getMessage());
            return [
                'table' => $tableName,
                'count' => 0,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Método público para substituir o privado do BackupModel
     */
    public function updateSyncControlPublic($tableName, $recordCount) {
        // Contar registros reais na tabela
        $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
        $countStmt = oci_parse($this->localConn, $countSql);
        oci_execute($countStmt);
        $total = oci_fetch_assoc($countStmt)['TOTAL'];
        oci_free_statement($countStmt);
        
        // Atualizar ou inserir em tasy_sync_control
        $sql = "MERGE INTO TASY_SYNC_CONTROL t
                USING (SELECT :table_name as table_name, :total as total FROM dual) s
                ON (t.table_name = s.table_name)
                WHEN MATCHED THEN
                    UPDATE SET last_sync = SYSTIMESTAMP, record_count = :total
                WHEN NOT MATCHED THEN
                    INSERT (table_name, last_sync, record_count)
                    VALUES (:table_name, SYSTIMESTAMP, :total)";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_bind_by_name($stmt, ':total', $total);
        oci_execute($stmt);
        oci_free_statement($stmt);
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
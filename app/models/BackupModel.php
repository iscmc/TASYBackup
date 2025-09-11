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

require_once __DIR__ . '/../config/database.php';

class BackupModel {
    protected $sourceConn;
    protected $localConn;
    
    public function __construct() {
        $this->connectToDatabases();
        $this->initializeSyncTable();
    }

    public function testConnections() {
        $result = ['source' => 'inactive', 'local' => 'inactive'];
        
        if ($this->sourceConn) {
            $stmt = oci_parse($this->sourceConn, "SELECT 1 FROM DUAL");
            if (oci_execute($stmt)) $result['source'] = 'active';
            oci_free_statement($stmt);
        }
        
        if ($this->localConn) {
            $stmt = oci_parse($this->localConn, "SELECT 1 FROM DUAL");
            if (oci_execute($stmt)) $result['local'] = 'active';
            oci_free_statement($stmt);
        }
        
        return $result;
    }

    protected function connectToDatabases() {
        // Conexão com Oracle Cloud
        $source = DatabaseConfig::$tasyDb;
        $sourceTns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(Host=".$source['host'].")(Port=".$source['port']."))
                     (CONNECT_DATA=(SERVICE_NAME=".$source['service_name'].")))";
        
        $this->sourceConn = oci_connect($source['user'], $source['pass'], $sourceTns, $source['charset']);
        if (!$this->sourceConn) {
            throw new Exception("Falha na conexão com banco de origem: " . oci_error());
        }

        // Conexão com Oracle XE local
        $local = DatabaseConfig::$localDb;
        $localTns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(Host=".$local['host'].")(Port=".$local['port']."))
                     (CONNECT_DATA=(SID=".$local['sid'].")))";
        
        $this->localConn = oci_connect($local['user'], $local['pass'], $localTns, $local['charset']);
        if (!$this->localConn) {
            throw new Exception("Falha na conexão com banco local: " . oci_error());
        }
    }

    public function initializeSyncTable() {
        try {
            // Verificar/criar TASY_SYNC_CONTROL
            $sqlCheckControl = "BEGIN
                        EXECUTE IMMEDIATE 'SELECT 1 FROM TASY_SYNC_CONTROL WHERE ROWNUM = 1';
                        EXCEPTION
                            WHEN OTHERS THEN
                                IF SQLCODE = -942 THEN
                                    -- Tabela não existe, vamos criar
                                    EXECUTE IMMEDIATE 'CREATE TABLE TASY_SYNC_CONTROL (
                                        table_name VARCHAR2(100) PRIMARY KEY,
                                        last_sync TIMESTAMP,
                                        record_count NUMBER DEFAULT 0,
                                        status VARCHAR2(20) DEFAULT ''ACTIVE'',
                                        key_column VARCHAR2(100),
                                        sync_filter VARCHAR2(500)
                                    )';
                                ELSE
                                    RAISE;
                                END IF;
                        END;";
            
            $stmt = oci_parse($this->localConn, $sqlCheckControl);
            if (!oci_execute($stmt)) {
                throw new Exception("Falha ao verificar/criar tabela de controle");
            }
            oci_free_statement($stmt);

            // Verificar/criar TASY_SYNC_LOGS
            $sqlCheckLogs = "BEGIN
                        EXECUTE IMMEDIATE 'SELECT 1 FROM TASY_SYNC_LOGS WHERE ROWNUM = 1';
                        EXCEPTION
                            WHEN OTHERS THEN
                                IF SQLCODE = -942 THEN
                                    -- Tabela não existe, vamos criar
                                    EXECUTE IMMEDIATE 'CREATE TABLE TASY_SYNC_LOGS (
                                        log_id NUMBER GENERATED ALWAYS AS IDENTITY,
                                        log_time TIMESTAMP DEFAULT SYSTIMESTAMP,
                                        log_type VARCHAR2(20),
                                        log_message VARCHAR2(4000),
                                        table_name VARCHAR2(100),
                                        record_count NUMBER,
                                        sync_type VARCHAR2(50),
                                        CONSTRAINT pk_tasy_sync_logs PRIMARY KEY (log_id)
                                    )';
                                ELSE
                                    RAISE;
                                END IF;
                        END;";
            
            $stmt = oci_parse($this->localConn, $sqlCheckLogs);
            if (!oci_execute($stmt)) {
                throw new Exception("Falha ao verificar/criar tabela de logs");
            }
            oci_free_statement($stmt);

            // Inicializar registros para cada tabela
            foreach (DatabaseConfig::getTablesToSync() as $table) {
                DatabaseConfig::validarTabela($table);
                $this->initializeTableSyncControl($table);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao inicializar tabelas de sincronização: " . $e->getMessage());
            throw $e;
        }
    }

    private function initializeTableSyncControl($tableName) {
        // Obter a chave primária da tabela
        $primaryKey = $this->getPrimaryKeyColumn($tableName);
        
        $sql = "MERGE INTO TASY_SYNC_CONTROL t
                USING (SELECT :table_name AS table_name, :key_column AS key_column FROM dual) s
                ON (t.table_name = s.table_name)
                WHEN MATCHED THEN
                    UPDATE SET key_column = :key_column
                WHEN NOT MATCHED THEN
                    INSERT (table_name, key_column)
                    VALUES (:table_name, :key_column)";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_bind_by_name($stmt, ':key_column', $primaryKey);
        oci_execute($stmt);
        oci_free_statement($stmt);
    }

    public function getPrimaryKeyColumn($tableName) {
        // Tabelas especiais com chaves não padrão
        $specialKeys = [
            'USUARIO' => 'NM_USUARIO',
            'CPOE_DIETA' => 'NR_SEQUENCIA',
            // Adicione outras tabelas especiais conforme necessário
        ];
        
        if (isset($specialKeys[$tableName])) {
            return $specialKeys[$tableName];
        }
        
        // Consulta padrão para obter a chave primária
        $sql = "SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND cols.table_name = UPPER(:table_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_assoc($stmt);
            return $row['COLUMN_NAME'] ?? 'NR_SEQUENCIA'; // Fallback comum em sistemas TASY
        }
        
        return 'NR_SEQUENCIA'; // Fallback padrão para sistemas TASY
    }

    public function getUltimoSync($tableName) {
        $sql = "SELECT last_sync FROM TASY_SYNC_CONTROL WHERE table_name = :table_name";
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        
        if (!oci_execute($stmt)) {
            return null;
        }
        
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $row['LAST_SYNC'] ?? null;
    }

    public function fetchNewRecords($tableName, $lastSync = null) {
        // Verifica se a tabela está configurada
        $tableConfig = DatabaseConfig::getTableConfig($tableName);
        if (!$tableConfig) {
            throw new Exception("Tabela {$tableName} não está configurada para sincronização");
        }

        $controlColumn = $tableConfig['control_column'];
        $primaryKey = $tableConfig['key_column'];
        
        $sql = "SELECT * FROM TASY.{$tableName}";
        $whereClauses = [];
        $params = [];

        // Para a tabela USUARIO, pega todos os registros
        if ($tableName !== 'USUARIO' && $controlColumn) {
            // Calcula o timestamp de 48 horas atrás
            $limiteTempo = date('d-M-Y H:i:s', strtotime('-48 hours'));
            $whereClauses[] = "{$controlColumn} >= TO_TIMESTAMP(:limiteTempo, 'DD-MON-YYYY HH24:MI:SS')";
            $params[':limiteTempo'] = $limiteTempo;
        }
        
        // Se houver lastSync (última sincronização), adiciona como filtro adicional
        if ($lastSync && $controlColumn && $this->colunaExiste($tableName, $controlColumn)) {
            // Formatar a data corretamente para a comparação
            $formattedLastSync = $this->formatOracleDate($lastSync);
            $whereClauses[] = "{$controlColumn} > TO_TIMESTAMP(:lastSync, 'DD-MON-YYYY HH24:MI:SS')";
            $params[':lastSync'] = $formattedLastSync;
        } elseif ($lastSync) {
            $lastId = $this->getLastRecordId($tableName, $primaryKey);
            if ($lastId !== null) {
                $whereClauses[] = "{$primaryKey} > :lastId";
                $params[':lastId'] = $lastId;
            }
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $orderColumn = $controlColumn ?: $primaryKey; // Ordenação
        if ($orderColumn && $this->colunaExiste($tableName, $orderColumn)) {
            $sql .= " ORDER BY {$orderColumn}";
        }

        // Execução segura
        $stmt = oci_parse($this->sourceConn, $sql);
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $value);
        }

        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            throw new Exception("Erro ao buscar registros em {$tableName}: " . $error['message']);
        }

        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }

        oci_free_statement($stmt);
        return $results;
    }

    private function tabelaExiste($tableName, $isSource = false) {
        $conn = $isSource ? $this->sourceConn : $this->localConn;
        $sql = "SELECT COUNT(*) FROM user_tables WHERE table_name = UPPER(:table_name)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        
        if (!oci_execute($stmt)) {
            return false;
        }
        
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return ($row['COUNT(*)'] > 0);
    }

    private function getControlColumn($tableName) {
        // Primeiro verifica se a tabela existe
        if (!$this->tabelaExiste($tableName, true)) {
            return null;
        }

        // Mapeamento explícito de colunas de controle por tabela
        $tableControlColumns = [
            'USUARIO' => 'DT_ATUALIZACAO',
            'CPOE_DIETA' => 'DT_ATUALIZACAO',
            'CPOE_MATERIAL' => 'DT_ATUALIZACAO',
            'CPOE_PROCEDIMENTO' => 'DT_ATUALIZACAO',
            'CPOE_GASOTERAPIA' => 'DT_ATUALIZACAO',
            'CPOE_RECOMENDACAO' => 'DT_ATUALIZACAO',
            'CPOE_HEMOTERAPIA' => 'DT_ATUALIZACAO',
            'CPOE_DIALISE' => 'DT_ATUALIZACAO',
            'CPOE_INTERVENCAO' => 'DT_ATUALIZACAO',
            'CPOE_ANATOMIA_PATOLOGICA' => 'DT_ATUALIZACAO'
        ];

        // Verifica se a tabela tem um mapeamento explícito
        if (isset($tableControlColumns[$tableName])) {
            $column = $tableControlColumns[$tableName];
            // Verifica se a coluna existe na tabela
            if ($this->colunaExiste($tableName, $column)) {
                return $column;
            }
        }

        // Fallback para colunas comuns caso não tenha mapeamento explícito
        $commonColumns = ['DT_ATUALIZACAO', 'DT_ALTERACAO', 'LAST_UPDATE', 'MODIFICATION_DATE'];
        
        foreach ($commonColumns as $col) {
            if ($this->colunaExiste($tableName, $col)) {
                return $col;
            }
        }
        
        return null;
    }

    private function colunaExiste($tableName, $columnName) {
        $sql = "SELECT COUNT(*) FROM USER_TAB_COLUMNS 
                WHERE TABLE_NAME = UPPER(:table_name) 
                AND COLUMN_NAME = UPPER(:column_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_bind_by_name($stmt, ':column_name', $columnName);
        
        oci_execute($stmt);
        $count = oci_fetch_assoc($stmt)['COUNT(*)'];
        oci_free_statement($stmt);
        
        return $count > 0;
    }

    private function getLastRecordId($tableName, $primaryKey) {
        $sql = "SELECT MAX({$primaryKey}) AS last_id FROM {$tableName}";
        $stmt = oci_parse($this->localConn, $sql);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_assoc($stmt);
            return $row['LAST_ID'] ?? null;
        }
        
        return null;
    }

    public function insertDataToLocal($tableName, $data) {
        if (empty($data)) return;

        // Verificar se é a tabela USUARIO e preparar tratamento especial
        $isUsuarioTable = ($tableName === 'USUARIO');
        
        // Obter colunas do primeiro registro
        $columns = array_keys($data[0]);
        $columnsStr = implode(', ', $columns);
        $bindStr = ':' . implode(', :', $columns);
        
        // Usar MERGE para evitar violação de constraint
        $mergeSql = "MERGE INTO {$tableName} target
                    USING (SELECT ";
        
        // Construir parte das colunas para o USING
        $usingColumns = [];
        foreach ($columns as $col) {
            $usingColumns[] = ":{$col} AS {$col}";
        }
        $mergeSql .= implode(', ', $usingColumns) . " FROM dual) source
                    ON (target.NM_USUARIO_PESQUISA = source.NM_USUARIO_PESQUISA";
        
        // Para tabela USUARIO, adicionar condição extra na cláusula ON
        if ($isUsuarioTable) {
            $mergeSql .= " AND target.NM_USUARIO = source.NM_USUARIO";
        }
        $mergeSql .= ")
                    WHEN MATCHED THEN
                        UPDATE SET ";
        
        // Construir parte do UPDATE (todas colunas exceto a chave)
        $updateCols = [];
        foreach ($columns as $col) {
            if ($col !== 'NM_USUARIO_PESQUISA' && (!$isUsuarioTable || $col !== 'NM_USUARIO')) {
                $updateCols[] = "target.{$col} = source.{$col}";
            }
        }
        $mergeSql .= implode(', ', $updateCols) . "
                    WHEN NOT MATCHED THEN
                        INSERT ({$columnsStr}) VALUES ({$bindStr})";
        
        $stmt = oci_parse($this->localConn, $mergeSql);
        
        // Desativar triggers temporariamente para tabela USUARIO
        if ($isUsuarioTable) {
            $disableSql = "ALTER TRIGGER USU_LOCALIDADE_AFTINS DISABLE";
            $disableStmt = oci_parse($this->localConn, $disableSql);
            oci_execute($disableStmt);
            oci_free_statement($disableStmt);
        }
        
        foreach ($data as $row) {
            foreach ($row as $col => $val) {
                // Converter datas para formato Oracle
                if (preg_match('/^DT_|DATE_/', $col) && $val !== null) {
                    $val = $this->formatDateForOracle($val);
                }
                oci_bind_by_name($stmt, ":{$col}", $row[$col]);
            }
            
            if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                oci_rollback($this->localConn);
                throw new Exception("Erro ao sincronizar dados em {$tableName}: " . oci_error($stmt)['message']);
            }
        }
        
        oci_commit($this->localConn);
        oci_free_statement($stmt);
        
        // Reativar triggers se necessário
        if ($isUsuarioTable) {
            $enableSql = "ALTER TRIGGER USU_LOCALIDADE_AFTINS ENABLE";
            $enableStmt = oci_parse($this->localConn, $enableSql);
            oci_execute($enableStmt);
            oci_free_statement($enableStmt);
        }
    }

    // Adicionar este novo método auxiliar para formatação de datas
    private function formatDateForOracle($dateString) {
        if (empty($dateString)) return null;
        
        try {
            $date = new DateTime($dateString);
            return $date->format('d-M-Y H:i:s');
        } catch (Exception $e) {
            // Tentar detectar formato automaticamente
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
                return date('d-M-Y H:i:s', strtotime($dateString));
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $dateString)) {
                return date('d-M-Y H:i:s', strtotime(str_replace('/', '-', $dateString)));
            }
            return null;
        }
    }

    private function isColumnNullable($tableName, $columnName) {
        $sql = "SELECT nullable FROM USER_TAB_COLUMNS 
                WHERE TABLE_NAME = UPPER(:table_name) 
                AND COLUMN_NAME = UPPER(:column_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_bind_by_name($stmt, ':column_name', $columnName);
        
        oci_execute($stmt);
        $result = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $result['NULLABLE'] === 'Y';
    }

    public function updateSyncControl($tableName, $count) {
        $sql = "UPDATE TASY_SYNC_CONTROL 
                SET last_sync = TO_TIMESTAMP(:lastSync, 'DD-MON-YYYY HH24:MI:SS.FF'), 
                    record_count = record_count + :count 
                WHERE table_name = :table_name";
        
        $currentTime = date('d-M-Y H:i:s');
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':lastSync', $currentTime);
        oci_bind_by_name($stmt, ':count', $count);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        
        if (!oci_execute($stmt)) {
            throw new Exception("Erro ao atualizar controle de sincronização: " . oci_error($stmt)['message']);
        }
        
        oci_free_statement($stmt);
    }

    public function getLocalConnection() {
        return $this->localConn;
    }

    public function getSourceConnection() {
        return $this->sourceConn;
    }

    public function debugTableInfo($tableName) {
        if (!$this->tabelaExiste($tableName, true)) {
            return "Tabela {$tableName} não existe na origem";
        }

        $info = [
            'table' => $tableName,
            'primary_key' => $this->getPrimaryKeyColumn($tableName),
            'control_column' => $this->getControlColumn($tableName),
            'columns' => $this->getTableColumns($tableName)
        ];

        return $info;
    }

    private function getTableColumns($tableName) {
        $sql = "SELECT column_name, data_type, nullable 
                FROM user_tab_columns 
                WHERE table_name = UPPER(:table_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_execute($stmt);
        
        $columns = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $columns[] = $row;
        }
        
        oci_free_statement($stmt);
        return $columns;
    }

    // Método auxiliar melhorado
    protected function validarTabela($tableName) {
        if (!in_array(strtoupper($tableName), DatabaseConfig::getConfiguredTables())) {
            throw new Exception("Tentativa de acessar tabela não configurada: {$tableName}");
        }

        // Verifica se a tabela existe no Oracle local
        $stmt = oci_parse($this->localConn, 
            "SELECT 1 FROM user_tables WHERE table_name = :table_name");
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_execute($stmt);
        
        if (!oci_fetch($stmt)) {
            throw new Exception("Tabela não existe no banco local: $tableName");
        }
    }

    /**
     * Registra a sincronização no banco de dados
     * 
     * @param string $table Nome da tabela
     * @param int $count Número de registros
     * @param string $type Tipo de sincronização
     */
    public function logSyncToDatabase($table, $count, $type) {
        $sql = "INSERT INTO TASY_SYNC_LOGS (
                    log_time, 
                    table_name, 
                    record_count, 
                    sync_type
                ) VALUES (
                    SYSTIMESTAMP, 
                    :table_name, 
                    :record_count, 
                    :sync_type
                )";
        
        $stmt = oci_parse($this->localConn, $sql);
        oci_bind_by_name($stmt, ':table_name', $table);
        oci_bind_by_name($stmt, ':record_count', $count);
        oci_bind_by_name($stmt, ':sync_type', $type);
        
        if (!oci_execute($stmt)) {
            throw new Exception("Database log error: " . oci_error($stmt)['message']);
        }
        
        oci_free_statement($stmt);
    }

    private function formatOracleDate($dateString) {
        if (empty($dateString)) return null;

        // Se já estiver no formato Oracle, retorna sem conversão
        if (preg_match('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}$/', $dateString)) {
            return $dateString;
        }

        try {
            // Tenta converter de formato ISO (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
                $date = new DateTime($dateString);
                return $date->format('d-M-Y H:i:s');
            }
            // Tenta converter de formato brasileiro (DD/MM/YYYY)
            elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $dateString)) {
                $date = DateTime::createFromFormat('d/m/Y H:i:s', $dateString);
                if ($date === false) {
                    $date = DateTime::createFromFormat('d/m/Y', $dateString);
                }
                return $date->format('d-M-Y H:i:s');
            }
            // Tenta converter timestamp Unix
            elseif (is_numeric($dateString)) {
                return date('d-M-Y H:i:s', $dateString);
            }
        } catch (Exception $e) {
            error_log("Erro ao converter data: {$dateString} - " . $e->getMessage());
        }

        return null;
    }

}
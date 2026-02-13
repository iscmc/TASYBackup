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
            'CPOE_GASOTERAPIA' => 'NR_SEQUENCIA',
            'CPOE_PROCEDIMENTO' => 'NR_SEQUENCIA',
            'CPOE_MATERIAL' => 'NR_SEQUENCIA',
            'CPOE_RECOMENDACAO' => 'NR_SEQUENCIA',
            'CPOE_HEMOTERAPIA' => 'NR_SEQUENCIA',
            'CPOE_DIALISE' => 'NR_SEQUENCIA',
            'CPOE_INTERVENCAO' => 'NR_SEQUENCIA',
            'PESSOA_FISICA' => 'CD_PESSOA_FISICA',
            'COMPL_PESSOA_FISICA' => 'CD_PESSOA_FISICA',
            'ATENDIMENTO_PACIENTE' => 'NR_ATENDIMENTO',
            'SETOR_ATENDIMENTO' => 'CD_SETOR_ATENDIMENTO',
            'UNIDADE_ATENDIMENTO' => 'NR_SEQ_INTERNO',
            'ATEND_PACIENTE_UNIDADE' => 'NR_SEQ_INTERNO',
            'MEDICO' => 'CD_PESSOA_FISICA',
            'PRESCR_MEDICA' => 'NR_PRESCRICAO',
            'CPOE_ANATOMIA_PATOLOGICA' => 'NR_SEQUENCIA'
        ];
        
        // Consulta padrão para obter a chave primária - CORRIGIDA COM SCHEMA
        $tableConfig = DatabaseConfig::getTableConfig($tableName);
        $schema = $tableConfig['schema'] ?? 'TASY';
        
        $sql = "SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND cons.owner = UPPER(:schema)
                AND cols.table_name = UPPER(:table_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':schema', $schema);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_assoc($stmt);
            return $row['COLUMN_NAME'] ?? 'NR_SEQUENCIA';
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
        try {
            error_log("=== INICIANDO SINCRONIZAÇÃO PARA: {$tableName} ===");
            
            // Verifica se a tabela está configurada
            $tableConfig = DatabaseConfig::getTableConfig($tableName);
            if (!$tableConfig) {
                throw new Exception("Tabela {$tableName} não está configurada para sincronização");
            }

            $controlColumn = $tableConfig['control_column'];
            $primaryKey = $tableConfig['key_column'];
            $whereClauses[] = "{$primaryKey} IS NOT NULL";
            $whereClauses[] = "{$primaryKey} != ''";
            $syncHours = $tableConfig['sync_hours'] ?? 24;
            $schema = $tableConfig['schema'] ?? 'TASY';

            // Para chaves numéricas, garantir que são > 0
            if (in_array($primaryKey, ['NR_SEQUENCIA', 'CD_PACIENTE', 'NR_ATENDIMENTO'])) {
                $whereClauses[] = "{$primaryKey} > 0";
            }
            
            // Calcula o timestamp das últimas X horas
            $limiteTempo = date('d-M-Y H:i:s', strtotime("-{$syncHours} hours"));
            
            error_log("Configuração: Coluna={$controlColumn}, Horas={$syncHours}, Limite={$limiteTempo}");

            // Constrói a query base
            $sql = "SELECT * FROM {$schema}.{$tableName}";
            $whereClauses = [];
            $params = [];

            // SEMPRE busca das últimas X horas (configurável)
            if ($controlColumn && $this->colunaExiste($tableName, $controlColumn, $schema)) {
                $whereClauses[] = "{$controlColumn} >= TO_TIMESTAMP(:limiteTempo, 'DD-MON-YYYY HH24:MI:SS')";
                $params[':limiteTempo'] = $limiteTempo;
                
                error_log("Filtro temporal aplicado: últimas {$syncHours}h");
            } else {
                error_log("AVISO: Coluna de controle {$controlColumn} não encontrada, buscando TODOS os registros");
            }
            
            // Para sincronizações futuras (incremental)
            if ($lastSync && $controlColumn && $this->colunaExiste($tableName, $controlColumn, $schema)) {
                $formattedLastSync = $this->formatOracleDate($lastSync);
                if ($formattedLastSync) {
                    $whereClauses[] = "{$controlColumn} > TO_TIMESTAMP(:lastSync, 'DD-MON-YYYY HH24:MI:SS')";
                    $params[':lastSync'] = $formattedLastSync;
                    error_log("Filtro incremental aplicado desde: {$formattedLastSync}");
                }
            }

            // Monta a query final
            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            // Ordenação para consistência
            $orderColumn = $controlColumn ?: $primaryKey;
            if ($orderColumn && $this->colunaExiste($tableName, $orderColumn, $schema)) {
                $sql .= " ORDER BY {$orderColumn}";
            }

            error_log("SQL Final: {$sql}");

            // Execução segura
            $stmt = oci_parse($this->sourceConn, $sql);
            foreach ($params as $key => $value) {
                // CORREÇÃO: Criar variável separada para bind
                $bindValue = $value;
                oci_bind_by_name($stmt, $key, $bindValue);
            }

            if (!oci_execute($stmt)) {
                $error = oci_error($stmt);
                error_log("ERRO na execução SQL: " . $error['message']);
                throw new Exception("Erro ao buscar registros em {$tableName}: " . $error['message']);
            }

            $results = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $results[] = $row;
            }

            oci_free_statement($stmt);
            
            error_log("=== RESULTADO: Encontrados " . count($results) . " registros em {$tableName} ===");
            return $results;
            
        } catch (Exception $e) {
            error_log("ERRO em fetchNewRecords: " . $e->getMessage());
            throw $e;
        }
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
            'PESSOA_FISICA' => 'DT_ATUALIZACAO',
            'COMPL_PESSOA_FISICA' => 'DT_ATUALIZACAO',
            'ATENDIMENTO_PACIENTE' => 'DT_ATUALIZACAO',
            'SETOR_ATENDIMENTO' => 'DT_ATUALIZACAO',
            'UNIDADE_ATENDIMENTO' => 'DT_ATUALIZACAO',
            'ATEND_PACIENTE_UNIDADE' => 'DT_ATUALIZACAO',
            'MEDICO' => 'DT_ATUALIZACAO',
            'PRESCR_MEDICA' => 'DT_ATUALIZACAO',
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

    private function colunaExiste($tableName, $columnName, $schema = null) {
        // Se schema não foi especificado, pega da configuração
        if ($schema === null) {
            $tableConfig = DatabaseConfig::getTableConfig($tableName);
            $schema = $tableConfig['schema'] ?? 'TASY';
        }
        
        $sql = "SELECT COUNT(*) FROM ALL_TAB_COLUMNS 
                WHERE OWNER = UPPER(:schema)
                AND TABLE_NAME = UPPER(:table_name) 
                AND COLUMN_NAME = UPPER(:column_name)";
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_bind_by_name($stmt, ':schema', $schema);
        oci_bind_by_name($stmt, ':table_name', $tableName);
        oci_bind_by_name($stmt, ':column_name', $columnName);
        
        oci_execute($stmt);
        $count = oci_fetch_assoc($stmt)['COUNT(*)'];
        oci_free_statement($stmt);
        
        error_log("Verificação coluna {$schema}.{$tableName}.{$columnName}: " . ($count > 0 ? 'EXISTE' : 'NÃO EXISTE'));
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

    /**
     * MÉTODO PRINCIPAL - Copia dados de qualquer tabela (baseado no que funcionou para CPOE_DIETA)
     */
    public function insertDataToLocal($tableName) {
        set_time_limit(600);
        try {
            error_log("=== SINCRONIZAÇÃO SIMPLIFICADA: {$tableName} ===");
            
            // 1. Obter último sync
            $lastSync = $this->getUltimoSync($tableName);
            error_log("Última sincronização: " . ($lastSync ?: 'Nunca'));
            
            // 2. Buscar dados NOVOS/ATUALIZADOS
            $remoteData = $this->fetchNewRecords($tableName, $lastSync);
            
            if (empty($remoteData)) {
                error_log("Nenhum dado novo encontrado para {$tableName}");
                $this->logSyncToDatabase($tableName, 0, 'INCREMENTAL');
                // ⭐ ATUALIZAÇÃO SIMPLES MESMO SEM DADOS NOVOS
                $this->updateSyncControlSimplificado($tableName);
                return [
                    'success' => true,
                    'message' => "Nenhum registro novo para {$tableName}",
                    'records_processed' => 0
                ];
            }
            
            error_log("Encontrados " . count($remoteData) . " registros novos em {$tableName}");
            
            // 3. Processar dados (com validação básica)
            $result = $this->processarDadosSimplificado($tableName, $remoteData);
            
            // 4. ⭐⭐ ATUALIZAÇÃO GARANTIDA DO CONTROLE - MESMO COM ERROS
            $this->updateSyncControlSimplificado($tableName);
            
            // 5. Registrar no log
            $this->logSyncToDatabase($tableName, $result['processed'], 'INCREMENTAL');
            
            return [
                'success' => true,
                'message' => "Tabela {$tableName} sincronizada com sucesso",
                'records_processed' => $result['processed'],
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'errors' => $result['errors']
            ];
            
        } catch (Exception $e) {
            error_log("ERRO em insertDataToLocal para {$tableName}: " . $e->getMessage());
            // ⭐⭐ ATUALIZAÇÃO DO CONTROLE MESMO EM CASO DE ERRO
            $this->updateSyncControlSimplificado($tableName);
            $this->logSyncToDatabase($tableName, 0, 'ERROR: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erro na tabela {$tableName}: " . $e->getMessage()
            ];
        }
    }

    /**
     * Processamento simplificado de dados
     */
    private function processarDadosSimplificado($tableName, $data) {
        if (empty($data)) {
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
        }

        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $errors = 0;

        foreach ($data as $row) {
            try {
                $result = $this->inserirOuAtualizarRegistro($tableName, $row, $primaryKey);
                if ($result === 'INSERT') {
                    $inserted++;
                    $processed++;
                } elseif ($result === 'UPDATE') {
                    $updated++;
                    $processed++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("Erro no registro: " . $e->getMessage());
            }
        }

        // Commit final dos dados
        oci_commit($this->localConn);

        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors
        ];
    }

     /**
     * Inserir ou atualizar registro individual
     */
    private function inserirOuAtualizarRegistro($tableName, $row, $primaryKey) {
        // CORREÇÃO EXCLUSIVAMENTE PARA TABELA COMPL_PESSOA_FISICA
        if ($tableName === 'COMPL_PESSOA_FISICA') {
            if (!isset($row['CD_PESSOA_FISICA']) || !isset($row['NR_SEQUENCIA'])) {
                throw new Exception("Chaves compostas CD_PESSOA_FISICA e NR_SEQUENCIA inválidas");
            }

            $cd_pessoa = $row['CD_PESSOA_FISICA'];
            $nr_sequencia = $row['NR_SEQUENCIA'];
            
            if ($this->isNewRecord($tableName, $primaryKey, ['CD_PESSOA_FISICA' => $cd_pessoa, 'NR_SEQUENCIA' => $nr_sequencia])) {
                $this->inserirRegistro($tableName, $row);
                return 'INSERT';
            } else {
                $this->atualizarRegistroComposta($tableName, $row, $cd_pessoa, $nr_sequencia);
                return 'UPDATE';
            }
        } else {
            // Código original para outras tabelas
            if (!isset($row[$primaryKey]) || empty($row[$primaryKey])) {
                throw new Exception("Chave primária inválida");
            }

            $keyValue = $row[$primaryKey];
            if ($this->isNewRecord($tableName, $primaryKey, $keyValue)) {
                $this->inserirRegistro($tableName, $row);
                return 'INSERT';
            } else {
                $this->atualizarRegistro($tableName, $row, $primaryKey, $keyValue);
                return 'UPDATE';
            }
        }
    }

     /**
     * Atualizar registro individual
     */
    private function atualizarRegistro($tableName, $row, $primaryKey, $keyValue) {
        $updateCols = [];
        foreach ($row as $col => $val) {
            if ($col !== $primaryKey) {
                $updateCols[] = "{$col} = :{$col}";
            }
        }
        
        $sql = "UPDATE {$tableName} SET " . implode(', ', $updateCols) . " WHERE {$primaryKey} = :primary_key";
        $stmt = oci_parse($this->localConn, $sql);
        
        foreach ($row as $col => $val) {
            if ($col !== $primaryKey) {
                oci_bind_by_name($stmt, ":{$col}", $row[$col]);
            }
        }
        oci_bind_by_name($stmt, ":primary_key", $keyValue);
        
        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($stmt);
            throw new Exception("Erro ao atualizar: " . $error['message']);
        }
        
        oci_free_statement($stmt);
    }

    /**
     * Método público para forçar atualização imediata do controle
     */
    public function forcarAtualizacaoControle($tableName = null) {
        $tables = $tableName ? [$tableName] : DatabaseConfig::getTablesToSync();
        $resultados = [];
        
        foreach ($tables as $table) {
            try {
                $this->updateSyncControlSimplificado($table);
                $resultados[$table] = ['success' => true, 'message' => 'Controle atualizado'];
            } catch (Exception $e) {
                $resultados[$table] = ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        return $resultados;
    }

     /**
     * Inserir registro individual
     */
    private function inserirRegistro($tableName, $row) {
        $columns = array_keys($row);
        $columnsStr = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);
        
        $sql = "INSERT INTO {$tableName} ({$columnsStr}) VALUES ({$placeholders})";
        $stmt = oci_parse($this->localConn, $sql);
        
        foreach ($row as $col => $val) {
            oci_bind_by_name($stmt, ":{$col}", $row[$col]);
        }
        
        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($stmt);
            throw new Exception("Erro ao inserir: " . $error['message']);
        }
        
        oci_free_statement($stmt);
    }   

    /**
     * Busca dados da tabela remota (últimas 72 horas) // alterei para 24 horas aqui e na linha 226
     */
    private function fetchDataFromRemote($tableName) {
        $tableConfig = DatabaseConfig::getTableConfig($tableName);
        $schema = $tableConfig['schema'] ?? 'TASY';
        $qualifiedTableName = "{$schema}.{$tableName}";
        
        $sql = "SELECT * FROM {$qualifiedTableName} 
                WHERE DT_ATUALIZACAO >= SYSDATE - 1 
                OR DT_ATUALIZACAO_NREC >= SYSDATE - 1"; //eram 3 e eu alterei para 1
        $stmt = oci_parse($this->sourceConn, $sql);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            throw new Exception("Erro ao buscar dados: " . $error['message']);
        }
        
        $data = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $data[] = $row;
        }
        
        oci_free_statement($stmt);
        return $data;
    }

    /**
     * VALIDAÇÃO ULTRA-RIGOROSA para tabelas CPOE_* - CORREÇÃO DO PROBLEMA
     */
    private function validarDadosCPOE($tableName, $data) {
        $dadosValidos = [];
        $dadosInvalidos = [];
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        error_log("=== VALIDAÇÃO ULTRA-RIGOROSA CPOE: {$tableName} ===");
        
        foreach ($data as $index => $row) {
            $valido = true;
            $motivos = [];
            
            // VERIFICAÇÃO 1: Chave primária EXISTE e não é NULL
            if (!isset($row[$primaryKey])) {
                $valido = false;
                $motivos[] = "Chave primária {$primaryKey} não existe no registro";
                error_log("❌ REGISTRO {$index}: Chave primária {$primaryKey} NÃO EXISTE");
            } 
            // VERIFICAÇÃO 2: Chave primária não é NULL ou vazia
            elseif ($row[$primaryKey] === null || $row[$primaryKey] === '') {
                $valido = false;
                $motivos[] = "Chave primária {$primaryKey} é NULL/VAZIA";
                error_log("❌ REGISTRO {$index}: Chave primária {$primaryKey} é NULL - Valor: '" . $row[$primaryKey] . "'");
            }
            // VERIFICAÇÃO 3: Para NR_SEQUENCIA, deve ser numérico e > 0
            elseif ($primaryKey === 'NR_SEQUENCIA') {
                $nrSequencia = $row[$primaryKey];
                if (!is_numeric($nrSequencia)) {
                    $valido = false;
                    $motivos[] = "NR_SEQUENCIA não é numérico: '{$nrSequencia}'";
                    error_log("❌ REGISTRO {$index}: NR_SEQUENCIA não numérico: '{$nrSequencia}'");
                } elseif ($nrSequencia <= 0) {
                    $valido = false;
                    $motivos[] = "NR_SEQUENCIA deve ser > 0: '{$nrSequencia}'";
                    error_log("❌ REGISTRO {$index}: NR_SEQUENCIA <= 0: '{$nrSequencia}'");
                }
            }
            
            // VERIFICAÇÃO 4: Datas válidas
            foreach ($row as $col => $val) {
                if (preg_match('/^DT_/', $col) && $val !== null && $val !== '') {
                    $dataFormatada = $this->formatDateForOracle($val);
                    if ($dataFormatada === null) {
                        $valido = false;
                        $motivos[] = "Data inválida em {$col}: '{$val}'";
                        error_log("❌ REGISTRO {$index}: Data inválida {$col}: '{$val}'");
                        break;
                    }
                }
            }
            
            if ($valido) {
                $dadosValidos[] = $row;
                //error_log("✅ REGISTRO {$index}: VÁLIDO - {$primaryKey} = '{$row[$primaryKey]}'"); //log comentado
            } else {
                $dadosInvalidos[] = [
                    'index' => $index,
                    'motivos' => $motivos,
                    'chave_primaria' => $primaryKey,
                    'valor_chave' => $row[$primaryKey] ?? 'NULL',
                    'dados' => $this->limitarDadosParaLog($row, 3)
                ];
            }
        }
        
        // Log estatístico detalhado
        error_log("=== RESULTADO VALIDAÇÃO CPOE: {$tableName} ===");
        error_log("TOTAL: " . count($data) . " | VÁLIDOS: " . count($dadosValidos) . " | INVÁLIDOS: " . count($dadosInvalidos));
        
        if (!empty($dadosInvalidos)) {
            // Agrupar problemas por tipo
            $problemasAgrupados = [];
            foreach ($dadosInvalidos as $invalido) {
                foreach ($invalido['motivos'] as $motivo) {
                    $problemasAgrupados[$motivo] = ($problemasAgrupados[$motivo] ?? 0) + 1;
                }
            }
            
            error_log("PROBLEMAS DETECTADOS:");
            foreach ($problemasAgrupados as $problema => $quantidade) {
                error_log("  - {$problema}: {$quantidade} registros");
            }
            
            // Mostrar exemplos dos primeiros 5 problemas
            for ($i = 0; $i < min(5, count($dadosInvalidos)); $i++) {
                $inv = $dadosInvalidos[$i];
                error_log("  EXEMPLO {$i}: {$inv['chave_primaria']} = '{$inv['valor_chave']}'");
                error_log("    Motivos: " . implode(', ', $inv['motivos']));
            }
        }
        
        return [
            'validos' => $dadosValidos,
            'invalidos' => $dadosInvalidos
        ];
    }

    /**
     * Copia dados usando MERGE (INSERT/UPDATE) - BASEADO NO QUE FUNCIONOU
     */
    private function copyDataWithMerge($tableName, $data) {
        if (empty($data)) {
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0, 'invalidos' => 0];
        }

        error_log("=== INICIANDO CÓPIA COM VALIDAÇÃO ULTRA-RIGOROSA: {$tableName} ===");
        
        // ⭐⭐ CORREÇÃO: Debug antes de qualquer processamento ⭐⭐
        $this->debugProcessamento($tableName, $data);
        
        // VALIDAÇÃO ULTRA-RIGOROSA para tabelas CPOE
        if (strpos($tableName, 'CPOE_') === 0) {
            $validacao = $this->validarDadosCPOE($tableName, $data);
        } else {
            $validacao = $this->validarDadosParaInsercao($tableName, $data);
        }
        
        $dadosValidos = $validacao['validos'];
        $dadosInvalidos = $validacao['invalidos'];
        
        if (empty($dadosValidos)) {
            error_log("❌ CÓPIA ABORTADA: NENHUM registro válido para {$tableName}");
            return [
                'processed' => 0, 
                'inserted' => 0, 
                'updated' => 0, 
                'errors' => 0, 
                'invalidos' => count($dadosInvalidos)
            ];
        }
        
        error_log("✅ VALIDAÇÃO CONCLUÍDA: " . count($dadosValidos) . " válidos de " . count($data) . " totais");

        // Obter colunas do primeiro registro válido
        $columns = array_keys($dadosValidos[0]);
        $columnsStr = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);
        
        // Determinar chave primária
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        // Construir MERGE statement
        $sql = "MERGE INTO {$tableName} target
                USING (SELECT ";
        
        $usingColumns = [];
        foreach ($columns as $col) {
            $usingColumns[] = ":{$col} AS {$col}";
        }
        $sql .= implode(', ', $usingColumns) . " FROM dual) source
                ON (target.{$primaryKey} = source.{$primaryKey})
                WHEN MATCHED THEN
                    UPDATE SET ";
        
        $updateCols = [];
        foreach ($columns as $col) {
            if ($col !== $primaryKey) {
                $updateCols[] = "target.{$col} = source.{$col}";
            }
        }
        $sql .= implode(', ', $updateCols) . "
                WHEN NOT MATCHED THEN
                    INSERT ({$columnsStr}) VALUES ({$placeholders})";

        error_log("SQL MERGE para {$tableName}: " . substr($sql, 0, 200) . "...");
        
        $stmt = oci_parse($this->localConn, $sql);
        
        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $errors = 0;
        
        foreach ($dadosValidos as $index => $row) {
            try {
                error_log("--- PROCESSANDO REGISTRO {$index} ---");
                error_log("Estado INICIAL - {$primaryKey}: '" . ($row[$primaryKey] ?? 'NULL') . "'");
                
                // Pré-processar valores
                $processedRow = [];
                
                foreach ($row as $col => $val) {
                    // Converter datas para formato Oracle se necessário
                    if (preg_match('/^DT_/', $col) && $val !== null && $val !== '') {
                        $formattedDate = $this->formatDateForOracle($val);
                        if ($formattedDate === null) {
                            error_log("🚨 ERRO CRÍTICO: Data inválida após validação - {$col}: '{$val}'");
                            $errors++;
                            continue 2; // Vai para o próximo registro
                        }
                        $processedRow[$col] = $formattedDate;
                    } else {
                        // Para valores não-data, manter original
                        $processedRow[$col] = $val;
                    }
                }
                
                // ⭐⭐ CORREÇÃO CRÍTICA: Verificar se a chave primária ainda existe após processamento ⭐⭐
                if (!isset($processedRow[$primaryKey])) {
                    error_log("🚨 ERRO CRÍTICO: {$primaryKey} NÃO EXISTE após processamento!");
                    $errors++;
                    continue;
                }
                
                // DOUBLE CHECK: Verificar novamente a chave primária
                if (!$this->validarRegistroParaInsercao($tableName, $processedRow)) {
                    error_log("🚨 ERRO CRÍTICO: Chave primária inválida após processamento: '{$processedRow[$primaryKey]}'");
                    $errors++;
                    continue;
                }
                
                error_log("Estado FINAL - {$primaryKey}: '" . ($processedRow[$primaryKey] ?? 'NULL') . "'");
                
                // ⭐⭐ CORREÇÃO CRÍTICA: Bind com variáveis locais e verificação rigorosa
                foreach ($processedRow as $col => $val) {
                // Criar variável local específica para cada coluna
                ${"bind_" . $col} = $val;
                
                // Log do bind para debug
                $bindValue = ${"bind_" . $col};
                $tipo = gettype($bindValue);
                $tamanho = is_string($bindValue) ? strlen($bindValue) : 'N/A';
                //error_log("  Bind CORRIGIDO: {$col} = '{$bindValue}' ({$tipo}, tamanho: {$tamanho})"); //log comentado
                
                // VERIFICAÇÃO CRÍTICA ESPECIAL PARA CHAVE PRIMÁRIA
                if ($col === $primaryKey) {
                    if (empty($bindValue) || $bindValue === null) {
                        error_log("🚨 ERRO CRÍTICO: Tentativa de bind com {$primaryKey} VAZIA/NULA: '{$bindValue}'");
                        $errors++;
                        continue 2; // Pula para o próximo registro
                    }
                    error_log("  ✅ CHAVE PRIMÁRIA CONFIRMADA: {$primaryKey} = '{$bindValue}'");
                }
                
                // ⭐⭐ CORREÇÃO PARA DATAS: Bind específico para colunas de data
                if (preg_match('/^DT_/', $col) && !empty($bindValue)) {
                    // Se é uma coluna de data e não está vazia, converter para formato Oracle TIMESTAMP
                    if (is_string($bindValue)) {
                        // Verificar se já está no formato Oracle correto
                        if (preg_match('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}$/', $bindValue)) {
                            // Já está no formato correto, usar como string
                            oci_bind_by_name($stmt, ":{$col}", $bindValue);
                            //error_log("  ✅ Data no formato Oracle: {$col} = '{$bindValue}'"); //log comentado
                        } else {
                            // Tentar converter para formato Oracle
                            $oracleDate = $this->formatDateForOracle($bindValue);
                            if ($oracleDate !== null) {
                                ${"bind_" . $col} = $oracleDate;
                                oci_bind_by_name($stmt, ":{$col}", ${"bind_" . $col});
                                //error_log("  ✅ Data convertida: {$col} = '{$oracleDate}'"); //log comentado
                            } else {
                                // Se não conseguiu converter, tratar como NULL
                                error_log("  ⚠️ Data inválida, definindo como NULL: {$col} = '{$bindValue}'");
                                ${"bind_" . $col} = null;
                                oci_bind_by_name($stmt, ":{$col}", ${"bind_" . $col}, -1, SQLT_CHR);
                            }
                        }
                    }
                } else {
                    // Para valores não-data ou datas vazias
                    if ($bindValue === null || $bindValue === '') {
                        oci_bind_by_name($stmt, ":{$col}", ${"bind_" . $col}, -1, SQLT_CHR);
                    } else {
                        oci_bind_by_name($stmt, ":{$col}", ${"bind_" . $col});
                    }
                }
            }
                
                // Executar e capturar erro detalhado
                error_log("  Executando MERGE para registro {$index}...");
                if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                    $error = oci_error($stmt);
                    error_log("🚨 ERRO na execução: " . $error['message']);
                    error_log("  Código: " . $error['code']);
                    error_log("  Offset: " . $error['offset']);
                    
                    // ⭐⭐ DEBUG ADICIONAL: Verificar qual coluna está causando o problema ⭐⭐
                    if (strpos($error['message'], 'NR_SEQUENCIA') !== false) {
                        error_log("  🚨 PROBLEMA ESPECÍFICO ENCONTRADO EM NR_SEQUENCIA");
                        error_log("  Valor atual de NR_SEQUENCIA: '" . ($processedRow[$primaryKey] ?? 'NULL') . "'");
                    }
                    
                    $errors++;
                    continue;
                }
                
                $processed++;
                
                // Verificar se foi INSERT ou UPDATE
                if ($this->isNewRecord($tableName, $primaryKey, $processedRow[$primaryKey])) {
                    $inserted++;
                    error_log("  ✅ INSERT realizado - {$primaryKey}: '{$processedRow[$primaryKey]}'");
                } else {
                    $updated++;
                    error_log("  ✅ UPDATE realizado - {$primaryKey}: '{$processedRow[$primaryKey]}'");
                }
                
                // Commit a cada 10 registros para melhor controle
                if ($processed % 10 === 0) {
                    oci_commit($this->localConn);
                    error_log("💾 Commit intermediário: {$processed}/" . count($dadosValidos) . " processados");
                }
                
            } catch (Exception $e) {
                error_log("🚨 EXCEÇÃO no registro {$index}: " . $e->getMessage());
                $errors++;
            }
        }
        
        // Commit final
        oci_commit($this->localConn);
        oci_free_statement($stmt);
        
        error_log("=== CÓPIA CONCLUÍDA: {$tableName} ===");
        error_log("Processados: {$processed} | Inseridos: {$inserted} | Atualizados: {$updated} | Erros: {$errors} | Inválidos: " . count($dadosInvalidos));
        
        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'invalidos' => count($dadosInvalidos)
        ];
    }

    /**
     * Determina chave primária para cada tabela
     */
    private function getPrimaryKeyForTable($tableName) {
        $primaryKeys = [
            'CPOE_DIETA' => 'NR_SEQUENCIA',
            'CPOE_PROCEDIMENTO' => 'NR_SEQUENCIA', 
            'CPOE_GASOTERAPIA' => 'NR_SEQUENCIA',
            'CPOE_MATERIAL' => 'NR_SEQUENCIA',
            'CPOE_RECOMENDACAO' => 'NR_SEQUENCIA',
            'CPOE_HEMOTERAPIA' => 'NR_SEQUENCIA',
            'CPOE_DIALISE' => 'NR_SEQUENCIA',
            'CPOE_INTERVENCAO' => 'NR_SEQUENCIA',
            'CPOE_ANATOMIA_PATOLOGICA' => 'NR_SEQUENCIA',
            'USUARIO' => 'NM_USUARIO',
            'REGRA_PADRAO_USUARIO' => 'NR_SEQUENCIA',
            'USER_LOCALE' => 'NM_USER',
            'PACIENTE' => 'CD_PACIENTE',
            'PESSOA_FISICA' => 'CD_PESSOA_FISICA',
            'COMPL_PESSOA_FISICA' => 'CD_PESSOA_FISICA',
            'ATENDIMENTO_PACIENTE' => 'NR_ATENDIMENTO',
            'SETOR_ATENDIMENTO' => 'CD_SETOR_ATENDIMENTO',
            'UNIDADE_ATENDIMENTO' => 'NR_SEQ_INTERNO',
            'ATEND_PACIENTE_UNIDADE' => 'NR_SEQ_INTERNO',
            'MEDICO' => 'CD_PESSOA_FISICA',
            'PRESCR_MEDICA' => 'NR_PRESCRICAO'
        ];
        
        return $primaryKeys[$tableName] ?? $this->getPrimaryKeyColumn($tableName);
    }

    /**
     * Verifica se registro é novo
     */
    private function isNewRecord($tableName, $primaryKey, $keyValue) {
        // CORREÇÃO ESPECÍFICA PARA COMPL_PESSOA_FISICA - verificar chave composta
        if ($tableName === 'COMPL_PESSOA_FISICA') {
            $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE CD_PESSOA_FISICA = :cd_pessoa AND NR_SEQUENCIA = :nr_sequencia";
            $stmt = oci_parse($this->localConn, $sql);
            
            // Extrair valores da chave composta
            $cd_pessoa = $keyValue['CD_PESSOA_FISICA'] ?? $keyValue;
            $nr_sequencia = $keyValue['NR_SEQUENCIA'] ?? null;
            
            oci_bind_by_name($stmt, ':cd_pessoa', $cd_pessoa);
            oci_bind_by_name($stmt, ':nr_sequencia', $nr_sequencia);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$primaryKey} = :key_value";
            $stmt = oci_parse($this->localConn, $sql);
            oci_bind_by_name($stmt, ':key_value', $keyValue);
        }
        
        oci_execute($stmt);
        $result = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $result['COUNT'] == 0;
    }

    // Adicionar este novo método auxiliar para formatação de datas
    private function formatDateForOracle($dateString) {
        if (empty($dateString) || $dateString === ' ' || $dateString === 'NULL') {
            return null;
        }

        // ⭐⭐ CORREÇÃO CRÍTICA: Se já está no formato "01-OCT-25", converter para "01-OCT-2025"
        if (preg_match('/^(\d{2}-[A-Za-z]{3}-\d{2})$/', $dateString, $matches)) {
            $ano = '20' . substr($matches[1], -2); // Converte 25 para 2025
            $novaData = substr($matches[1], 0, -2) . $ano . ' 00:00:00';
            //error_log("✅ Data convertida de '{$dateString}' para '{$novaData}'"); //log comentado
            return $novaData;
        }
        
        // ⭐⭐ NOVA CORREÇÃO: Se já está no formato '27-Sep-2025 00:00:00', retornar como está
        if (preg_match('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}$/', $dateString)) {
            return $dateString;
        }
        
        // Se já estiver no formato Oracle correto com milissegundos, retorna sem alteração
        if (preg_match('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}\.\d+$/', $dateString)) {
            return $dateString;
        }
        
        // Remove milissegundos se existirem
        if (preg_match('/^(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})\.\d+$/', $dateString, $matches)) {
            return $matches[1];
        }

        try {
            // Tenta converter de timestamp Oracle curto (01-JAN-25)
            if (preg_match('/^(\d{2}-[A-Za-z]{3}-\d{2})$/', $dateString)) {
                $timestamp = strtotime($dateString);
                if ($timestamp !== false) {
                    return date('d-M-Y H:i:s', $timestamp);
                }
            }
            
            // Tenta os formatos mais comuns
            $formats = [
                'Y-m-d H:i:s.u',  // 2024-01-15 14:30:25.123456
                'Y-m-d H:i:s',    // 2024-01-15 14:30:25
                'Y-m-d',          // 2024-01-15
                'd/m/Y H:i:s',    // 15/01/2024 14:30:25
                'd/m/Y',          // 15/01/2024
                'd-M-Y H:i:s',    // 15-JAN-2024 14:30:25
                'd-M-Y',          // 15-JAN-2024
                'd-M-y H:i:s',    // 15-JAN-24 14:30:25
                'd-M-y',          // 15-JAN-24
                'M-d-Y H:i:s',    // JAN-15-2024 14:30:25
            ];
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date->format('d-M-Y H:i:s');
                }
            }
            
            // Última tentativa com strtotime
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('d-M-Y H:i:s', $timestamp);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao converter data: {$dateString} - " . $e->getMessage());
        }
        
        // SE NÃO CONSEGUIR CONVERTER, RETORNA NULL E LOG DETALHADO
        error_log("ERRO CRÍTICO: Não foi possível converter a data: '{$dateString}'");
        return null;
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

    /**
     * Atualiza controle de sincronização com contagem TOTAL de registros
     */
    private function updateSyncControl($tableName) {
        try {
            // Contar TODOS os registros reais na tabela local
            $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
            $countStmt = oci_parse($this->localConn, $countSql);
            
            if (!oci_execute($countStmt)) {
                error_log("ERRO ao contar registros para {$tableName}: " . oci_error($countStmt));
                $total = 0;
            } else {
                $result = oci_fetch_assoc($countStmt);
                $total = $result['TOTAL'] ?? 0;
                error_log("✅ Contagem REAL de {$tableName}: {$total} registros");
            }
            oci_free_statement($countStmt);
            
            // Atualizar com MERGE 
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
            
            if (!oci_execute($stmt)) {
                $error = oci_error($stmt);
                error_log("❌ ERRO ao atualizar TASY_SYNC_CONTROL: " . $error['message']);
                throw new Exception("Falha ao atualizar controle: " . $error['message']);
            }
            
            // COMMIT EXPLÍCITO - ESSENCIAL
            oci_commit($this->localConn);
            oci_free_statement($stmt);
            
            error_log("🎯 Controle ATUALIZADO com SUCESSO: {$tableName} = {$total} registros");
            
        } catch (Exception $e) {
            error_log("🚨 EXCEÇÃO em updateSyncControl para {$tableName}: " . $e->getMessage());
            // Não faz rollback para não perder os dados já inseridos
        }
    }

    /**
     * ⭐⭐ MÉTODO SIMPLIFICADO PARA ATUALIZAR CONTROLE - GARANTIDO
     */
    private function updateSyncControlSimplificado($tableName) {
        $startTime = microtime(true);
        try {
            // 1. Contagem DIRETA e SIMPLES
            $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
            $countStmt = oci_parse($this->localConn, $countSql);
            
            if (!oci_execute($countStmt)) {
                error_log("❌ ERRO na contagem de {$tableName}");
                $total = 0;
            } else {
                $result = oci_fetch_assoc($countStmt);
                $total = $result['TOTAL'] ?? 0;
            }
            oci_free_statement($countStmt);
            
            // 2. Atualização DIRETA e SIMPLES
            $sql = "UPDATE TASY_SYNC_CONTROL 
                    SET last_sync = SYSTIMESTAMP, record_count = :total 
                    WHERE table_name = :table_name";
            
            $stmt = oci_parse($this->localConn, $sql);
            oci_bind_by_name($stmt, ':total', $total);
            oci_bind_by_name($stmt, ':table_name', $tableName);
            
            if (!oci_execute($stmt)) {
                $error = oci_error($stmt);
                error_log("❌ ERRO na atualização do controle: " . $error['message']);
                // NÃO lança exceção - apenas log e continua
            }
            
            oci_free_statement($stmt);
            
            // 3. ⭐⭐ COMMIT EXPLÍCITO E GARANTIDO
            if (!oci_commit($this->localConn)) {
                error_log("❌ ERRO no commit do controle");
                // Tenta novamente
                oci_commit($this->localConn);
            }
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            error_log("✅ CONTROLE ATUALIZADO: {$tableName} = {$total} registros ({$duration}ms)");
            
        } catch (Exception $e) {
            error_log("🚨 EXCEÇÃO em updateSyncControlSimplificado: " . $e->getMessage());
            // Tenta fazer commit mesmo com exceção
            @oci_commit($this->localConn);
        }
    }

    /**
     * Sincroniza todas as tabelas
     */
    public function syncAllTables() {
        $tables = [
            'CPOE_DIETA',
            'CPOE_PROCEDIMENTO', 
            'USUARIO',
            'REGRA_PADRAO_USUARIO',
            'USER_LOCALE'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $results[$table] = $this->insertDataToLocal($table);
        }
        
        return $results;
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

    /**
     * Corrige a contagem de registros para todas as tabelas
     * Use este método uma vez para corrigir os valores existentes
     */
    public function corrigirContagemRegistros() {
        try {
            $tables = DatabaseConfig::getTablesToSync();
            
            foreach ($tables as $tableName) {
                $countSql = "SELECT COUNT(*) as total_real FROM {$tableName}";
                $countStmt = oci_parse($this->localConn, $countSql);
                oci_execute($countStmt);
                $totalReal = oci_fetch_assoc($countStmt)['TOTAL_REAL'];
                oci_free_statement($countStmt);
                
                $updateSql = "UPDATE TASY_SYNC_CONTROL SET record_count = :total_real WHERE table_name = :table_name";
                $updateStmt = oci_parse($this->localConn, $updateSql);
                oci_bind_by_name($updateStmt, ':total_real', $totalReal);
                oci_bind_by_name($updateStmt, ':table_name', $tableName);
                oci_execute($updateStmt);
                oci_free_statement($updateStmt);
                
                error_log("Contagem corrigida para {$tableName}: {$totalReal} registros");
            }
            
            oci_commit($this->localConn);
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao corrigir contagem: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Debug de datas problemáticas
     */
    public function debugDates($tableName, $limit = 10) {
        $sql = "SELECT * FROM (SELECT * FROM {$tableName} ORDER BY DT_ATUALIZACAO DESC) WHERE ROWNUM <= {$limit}";
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_execute($stmt);
        
        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            foreach ($row as $col => $val) {
                if (preg_match('/^DT_/', $col) && $val) {
                    $results[] = [
                        'column' => $col,
                        'original' => $val,
                        'formatted' => $this->formatDateForOracle($val)
                    ];
                }
            }
        }
        oci_free_statement($stmt);
        
        return $results;
    }

    /**
     * Diagnóstico detalhado das datas problemáticas
     */
    public function diagnosticarDatasProblematicas($tableName) {
        error_log("=== DIAGNÓSTICO DE DATAS PARA: {$tableName} ===");
        
        // Buscar alguns registros para análise
        $sql = "SELECT * FROM {$tableName} WHERE ROWNUM <= 10";
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_execute($stmt);
        
        $problemas = [];
        
        while ($row = oci_fetch_assoc($stmt)) {
            foreach ($row as $col => $val) {
                if (preg_match('/^DT_/', $col) && $val) {
                    $formatada = $this->formatDateForOracle($val);
                    if ($formatada === null) {
                        $problemas[] = [
                            'coluna' => $col,
                            'valor_original' => $val,
                            'tipo' => gettype($val),
                            'tamanho' => strlen($val),
                            'bytes' => bin2hex(substr($val, 0, 10))
                        ];
                    }
                }
            }
        }
        oci_free_statement($stmt);
        
        if (!empty($problemas)) {
            error_log("DATAS PROBLEMÁTICAS ENCONTRADAS:");
            foreach ($problemas as $problema) {
                error_log(" - Coluna: {$problema['coluna']}");
                error_log("   Valor: '{$problema['valor_original']}'");
                error_log("   Tipo: {$problema['tipo']}, Tamanho: {$problema['tamanho']}");
                error_log("   Bytes: {$problema['bytes']}");
            }
        } else {
            error_log("Nenhuma data problemática encontrada nas primeiras 10 linhas");
        }
        
        return $problemas;
    }

    /**
     * SOLUÇÃO DE EMERGÊNCIA - Ignora registros com datas problemáticas
     */
    public function insertDataToLocalEmergency($tableName) {
        try {
            error_log("=== SINCRONIZAÇÃO DE EMERGÊNCIA PARA: {$tableName} ===");
            
            // Primeiro, diagnosticar o problema
            $problemas = $this->diagnosticarDatasProblematicas($tableName);
            
            // Buscar dados normalmente
            $lastSync = $this->getUltimoSync($tableName);
            $remoteData = $this->fetchNewRecords($tableName, $lastSync);
            
            if (empty($remoteData)) {
                error_log("Nenhum dado novo encontrado para {$tableName}");
                return ['success' => true, 'message' => "Nenhum registro novo", 'records_processed' => 0];
            }
            
            error_log("Processando " . count($remoteData) . " registros com filtro de emergência");
            
            // Filtrar registros com datas válidas
            $dadosValidos = [];
            foreach ($remoteData as $index => $row) {
                $valido = true;
                foreach ($row as $col => $val) {
                    if (preg_match('/^DT_/', $col) && $val) {
                        if ($this->formatDateForOracle($val) === null) {
                            error_log("Registro {$index} removido - data inválida em {$col}: '{$val}'");
                            $valido = false;
                            break;
                        }
                    }
                }
                if ($valido) {
                    $dadosValidos[] = $row;
                }
            }
            
            error_log("Registros válidos após filtro: " . count($dadosValidos) . " de " . count($remoteData));
            
            if (empty($dadosValidos)) {
                error_log("Nenhum registro válido após filtro de datas");
                return ['success' => false, 'message' => "Todos os registros têm datas inválidas"];
            }
            
            // Processar apenas os válidos
            $result = $this->copyDataWithMerge($tableName, $dadosValidos);
            
            $this->updateSyncControl($tableName);
            $this->logSyncToDatabase($tableName, $result['processed'], 'EMERGENCY');
            
            return [
                'success' => true,
                'message' => "Sincronização de emergência concluída",
                'records_processed' => $result['processed'],
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'errors' => $result['errors'],
                'filtered_out' => count($remoteData) - count($dadosValidos)
            ];
            
        } catch (Exception $e) {
            error_log("ERRO em sincronização de emergência: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Valida dados antes da inserção - CORREÇÃO PARA NR_SEQUENCIA NULL
     */
    private function validarDadosParaInsercao($tableName, $data) {
        $dadosValidos = [];
        $dadosInvalidos = [];
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        error_log("VALIDANDO DADOS: {$tableName} (Chave: {$primaryKey})");
        
        foreach ($data as $index => $row) {
            $valido = true;
            $motivos = [];
            
            // Verificação 1: Chave primária válida
            if (!$this->validarRegistroParaInsercao($tableName, $row)) {
                $valido = false;
                $motivos[] = "Chave primária {$primaryKey} inválida";
            }
            
            // Verificação 2: Datas válidas
            foreach ($row as $col => $val) {
                if (preg_match('/^DT_/', $col) && $val !== null && $val !== '') {
                    $dataFormatada = $this->formatDateForOracle($val);
                    if ($dataFormatada === null) {
                        $valido = false;
                        $motivos[] = "Data inválida em {$col}: '{$val}'";
                        break; // Uma data inválida já invalida o registro
                    }
                }
            }
            
            if ($valido) {
                $dadosValidos[] = $row;
            } else {
                $dadosInvalidos[] = [
                    'index' => $index,
                    'motivos' => $motivos,
                    'chave_primaria' => $primaryKey,
                    'valor_chave' => $row[$primaryKey] ?? 'NULL',
                    'dados' => $this->limitarDadosParaLog($row, 5)
                ];
            }
        }
        
        // Log detalhado dos problemas
        if (!empty($dadosInvalidos)) {
            error_log("VALIDACAO: {$tableName} - " . count($dadosInvalidos) . " INVÁLIDOS de " . count($data) . " totais");
            
            // Agrupar por tipo de problema para análise
            $problemasAgrupados = [];
            foreach ($dadosInvalidos as $invalido) {
                foreach ($invalido['motivos'] as $motivo) {
                    $problemasAgrupados[$motivo] = ($problemasAgrupados[$motivo] ?? 0) + 1;
                }
            }
            
            foreach ($problemasAgrupados as $problema => $quantidade) {
                error_log("  - {$problema}: {$quantidade} registros");
            }
            
            // Log dos primeiros 3 registros problemáticos para debug
            for ($i = 0; $i < min(3, count($dadosInvalidos)); $i++) {
                $inv = $dadosInvalidos[$i];
                error_log("  Exemplo {$i}: {$inv['chave_primaria']} = '{$inv['valor_chave']}' - " . implode(', ', $inv['motivos']));
            }
        } else {
            error_log("VALIDACAO: {$tableName} - TODOS os " . count($data) . " registros são VÁLIDOS");
        }
        
        return [
            'validos' => $dadosValidos,
            'invalidos' => $dadosInvalidos
        ];
    }

    /**
     * Limita dados para log (evita logs muito grandes)
     */
    private function limitarDadosParaLog($row, $maxColunas = 8) {
        $limitado = [];
        $count = 0;
        foreach ($row as $col => $val) {
            if ($count++ < $maxColunas) {
                $limitado[$col] = is_string($val) ? substr($val, 0, 50) : $val;
            }
        }
        if (count($row) > $maxColunas) {
            $limitado['...'] = '... mais ' . (count($row) - $maxColunas) . ' colunas';
        }
        return $limitado;
    }

    /**
     * Dá uma vasculhada geral na busca de dados problemáticos da tabela remota (Tasy)
     */
    public function investigarDadosRemotos($tableName, $limit = 20) {
        error_log("=== INVESTIGAÇÃO DE DADOS REMOTOS: {$tableName} ===");
        
        // OBTER CONFIGURAÇÃO DA TABELA para pegar o schema
        $tableConfig = DatabaseConfig::getTableConfig($tableName);
        $schema = $tableConfig['schema'] ?? 'TASY';
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        // Buscar dados com possíveis problemas - CORRIGIDO COM SCHEMA
        $sql = "SELECT * FROM {$schema}.{$tableName} 
                WHERE {$primaryKey} IS NULL 
                OR DT_ATUALIZACAO >= SYSDATE - 3 
                ORDER BY DT_ATUALIZACAO DESC";

        if ($limit > 0) {
            $sql = "SELECT * FROM ($sql) WHERE ROWNUM <= {$limit}";
        }
        
        $stmt = oci_parse($this->sourceConn, $sql);
        oci_execute($stmt);
        
        $resultados = [];
        $comProblemas = 0;
        
        while ($row = oci_fetch_assoc($stmt)) {
            $problemas = [];
            
            // Verificar chave primária
            if (empty($row[$primaryKey]) || $row[$primaryKey] === null) {
                $problemas[] = "CHAVE_PRIMARIA_NULL";
            }
            
            // Verificar datas
            foreach ($row as $col => $val) {
                if (preg_match('/^DT_/', $col) && $val) {
                    if ($this->formatDateForOracle($val) === null) {
                        $problemas[] = "DATA_INVALIDA_{$col}";
                    }
                }
            }
            
            $resultado = [
                'dados' => $this->limitarDadosParaLog($row, 6),
                'problemas' => $problemas
            ];
            
            if (!empty($problemas)) {
                $comProblemas++;
            }
            
            $resultados[] = $resultado;
        }
        oci_free_statement($stmt);
        
        error_log("RESULTADO INVESTIGAÇÃO:");
        error_log("- Total de registros analisados: " . count($resultados));
        error_log("- Registros com problemas: {$comProblemas}");
        error_log("- Chave primária: {$primaryKey}");
        error_log("- Schema usado: {$schema}");
        
        foreach ($resultados as $resultado) {
            if (!empty($resultado['problemas'])) {
                error_log("REGISTRO COM PROBLEMAS: " . implode(', ', $resultado['problemas']));
                error_log("  Dados: " . json_encode($resultado['dados']));
            }
        }       
        return $resultados;
    }

    /**
     * Valida se o registro tem chave primária válida
     */
    private function validarRegistroParaInsercao($tableName, $row) {
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        // Verificação 1: Chave primária existe no array?
        if (!isset($row[$primaryKey])) {
            error_log("REGISTRO INVALIDO: Chave primária {$primaryKey} não encontrada no registro");
            return false;
        }
        
        $keyValue = $row[$primaryKey];
        
        // Verificação 2: Não é NULL ou vazio
        if ($keyValue === null || $keyValue === '') {
            error_log("REGISTRO INVALIDO: Chave primária {$primaryKey} está NULL/VAZIA");
            return false;
        }
        
        // Verificação 3: Para chaves numéricas, validar formato
        if (in_array($primaryKey, ['NR_SEQUENCIA', 'CD_PACIENTE', 'NR_ATENDIMENTO'])) {
            if (!is_numeric($keyValue) || $keyValue <= 0) {
                error_log("REGISTRO INVALIDO: {$primaryKey} não é numérico válido: '{$keyValue}'");
                return false;
            }
        }
        
        // Verificação 4: Para chaves textuais, validar comprimento
        if (in_array($primaryKey, ['NM_USUARIO', 'NM_USER'])) {
            if (strlen(trim($keyValue)) === 0) {
                error_log("REGISTRO INVALIDO: {$primaryKey} está vazio ou só espaços");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Obtém o nome qualificado da tabela (com schema) para o banco origem
     */
    private function getQualifiedTableName($tableName, $isSource = true) {
        if ($isSource) {
            $tableConfig = DatabaseConfig::getTableConfig($tableName);
            $schema = $tableConfig['schema'] ?? 'TASY';
            return "{$schema}.{$tableName}";
        }
        return $tableName; // Para banco local não precisa de schema
    }

    /**
     * DIAGNÓSTICO DE EMERGÊNCIA - Identifica registros problemáticos
     */
    public function diagnosticoEmergencial($tableName, $limit = 50) {
        error_log("=== DIAGNÓSTICO DE EMERGÊNCIA: {$tableName} ===");
        
        $tableConfig = DatabaseConfig::getTableConfig($tableName);
        $schema = $tableConfig['schema'] ?? 'TASY';
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        
        // ⭐⭐ CORREÇÃO: Query apenas para registros NULL, sem comparações inválidas
        $sql = "SELECT {$primaryKey}, DT_ATUALIZACAO 
                FROM {$schema}.{$tableName} 
                WHERE {$primaryKey} IS NULL
                ORDER BY DT_ATUALIZACAO DESC";
        
        if ($limit > 0) {
            $sql = "SELECT * FROM ($sql) WHERE ROWNUM <= {$limit}";
        }
        
        error_log("SQL Diagnóstico Corrigido: {$sql}");
        
        $stmt = oci_parse($this->sourceConn, $sql);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            error_log("ERRO no diagnóstico: " . $error['message']);
            oci_free_statement($stmt);
            return [];
        }
        
        $problemas = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $problema = [
                'chave_primaria' => $primaryKey,
                'valor_chave' => $row[$primaryKey] ?? 'NULL',
                'tipo_valor' => gettype($row[$primaryKey] ?? 'NULL'),
                'comprimento' => strlen($row[$primaryKey] ?? ''),
                'dados' => $this->limitarDadosParaLog($row, 4)
            ];
            
            $problemas[] = $problema;
            error_log("PROBLEMA ENCONTRADO: {$primaryKey} = '{$problema['valor_chave']}'");
        }
        oci_free_statement($stmt);
        
        error_log("=== FIM DIAGNÓSTICO: " . count($problemas) . " problemas encontrados ===");
        return $problemas;
    }

    /**
     * Método público simples para obter a chave primária
     */
    public function getChavePrimaria($tableName) {
        return $this->getPrimaryKeyForTable($tableName);
    }
    
    /**
     * DEBUG DETALHADO do Processamento - CORREÇÃO DO PROBLEMA
     */
    private function debugProcessamento($tableName, $data) {
        error_log("=== DEBUG DETALHADO DO PROCESSAMENTO: {$tableName} ===");
        
        $primaryKey = $this->getPrimaryKeyForTable($tableName);
        $problemas = [];
        
        foreach ($data as $index => $row) {
            $status = "✅ VÁLIDO";
            $problema = null;
            
            // Verificar estado ORIGINAL
            if (!isset($row[$primaryKey]) || $row[$primaryKey] === null || $row[$primaryKey] === '') {
                $status = "🚨 ORIGINAL NULL";
                $problema = "Chave NULL desde o início";
            }
            elseif ($primaryKey === 'NR_SEQUENCIA' && (!is_numeric($row[$primaryKey]) || $row[$primaryKey] <= 0)) {
                $status = "🚨 ORIGINAL INVÁLIDO";
                $problema = "Chave inválida desde o início: '{$row[$primaryKey]}'";
            }
            
            if ($problema) {
                $problemas[] = [
                    'index' => $index,
                    'status' => $status,
                    'problema' => $problema,
                    'chave' => $row[$primaryKey] ?? 'NULL',
                    'dados' => $this->limitarDadosParaLog($row, 3)
                ];
            }
            
            error_log("Registro {$index}: {$status} - {$primaryKey} = '{$row[$primaryKey]}'");
        }
        
        if (!empty($problemas)) {
            error_log("=== PROBLEMAS IDENTIFICADOS ===");
            foreach ($problemas as $p) {
                error_log("Index {$p['index']}: {$p['problema']}");
                error_log("  Dados: " . json_encode($p['dados']));
            }
        }
        
        error_log("=== FIM DEBUG: " . count($problemas) . " problemas ===");
        return count($problemas) === 0;
    }

    private function processarEmLotes($tableName, $data, $tamanhoLote = 100) {
        $totalProcessado = 0;
        $lotes = array_chunk($data, $tamanhoLote);
        
        foreach ($lotes as $numeroLote => $lote) {
            error_log("Processando lote " . ($numeroLote + 1) . " de " . count($lotes));
            
            $resultadoLote = $this->copyDataWithMerge($tableName, $lote);
            $totalProcessado += $resultadoLote['processed'];
            
            // Commit explícito a cada lote
            oci_commit($this->localConn);
            
            // Pequena pausa para evitar sobrecarga
            usleep(100000); // 100ms
        }
        
        return $totalProcessado;
    }

    /**
     * Corrige imediatamente todas as contagens na TASY_SYNC_CONTROL
     */
    public function corrigirContagensImediatamente() {
        $tables = DatabaseConfig::getTablesToSync();
        $resultados = [];
        
        foreach ($tables as $tableName) {
            try {
                // Contar registros reais
                $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
                $countStmt = oci_parse($this->localConn, $countSql);
                oci_execute($countStmt);
                $total = oci_fetch_assoc($countStmt)['TOTAL'];
                oci_free_statement($countStmt);
                
                // Atualizar diretamente
                $updateSql = "UPDATE TASY_SYNC_CONTROL 
                            SET record_count = :total, last_sync = SYSTIMESTAMP 
                            WHERE table_name = :table_name";
                $updateStmt = oci_parse($this->localConn, $updateSql);
                oci_bind_by_name($updateStmt, ':total', $total);
                oci_bind_by_name($updateStmt, ':table_name', $tableName);
                oci_execute($updateStmt);
                oci_free_statement($updateStmt);
                
                $resultados[$tableName] = [
                    'success' => true,
                    'record_count' => $total,
                    'message' => "Contagem corrigida para {$total} registros"
                ];
                
                error_log("✅ {$tableName}: corrigido para {$total} registros");
                
            } catch (Exception $e) {
                $resultados[$tableName] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                error_log("❌ ERRO em {$tableName}: " . $e->getMessage());
            }
        }
        
        oci_commit($this->localConn);
        return $resultados;
    }

    /**
    * Verifica e corrige o estado atual do sync control
    */
    public function verificarEstadoSyncControl($tableName = null) {
        try {
            if ($tableName) {
                $sql = "SELECT table_name, last_sync, record_count FROM TASY_SYNC_CONTROL WHERE table_name = :table_name";
                $stmt = oci_parse($this->localConn, $sql);
                oci_bind_by_name($stmt, ':table_name', $tableName);
            } else {
                $sql = "SELECT table_name, last_sync, record_count FROM TASY_SYNC_CONTROL ORDER BY table_name";
                $stmt = oci_parse($this->localConn, $sql);
            }
            oci_execute($stmt);
            
            $resultados = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $resultados[] = [
                    'table_name' => $row['TABLE_NAME'],
                    'last_sync' => $row['LAST_SYNC'],
                    'record_count' => $row['RECORD_COUNT']
                ];
            }
            oci_free_statement($stmt);
            
            return $resultados;
            
        } catch (Exception $e) {
            error_log("ERRO ao verificar sync control: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Força a atualização imediata do sync control para uma tabela
     */
    public function forcarAtualizacaoSyncControl($tableName) {
        try {
            error_log("=== FORÇANDO ATUALIZAÇÃO DO SYNC CONTROL: {$tableName} ===");
            
            // Contar registros atuais
            $countSql = "SELECT COUNT(*) as total FROM {$tableName}";
            $countStmt = oci_parse($this->localConn, $countSql);
            oci_execute($countStmt);
            $total = oci_fetch_assoc($countStmt)['TOTAL'];
            oci_free_statement($countStmt);
            
            error_log("Registros contados em {$tableName}: {$total}");
            
            // Atualizar diretamente
            $updateSql = "UPDATE TASY_SYNC_CONTROL 
                        SET record_count = :total, last_sync = SYSTIMESTAMP 
                        WHERE table_name = :table_name";
            $updateStmt = oci_parse($this->localConn, $updateSql);
            oci_bind_by_name($updateStmt, ':total', $total);
            oci_bind_by_name($updateStmt, ':table_name', $tableName);
            
            if (!oci_execute($updateStmt)) {
                $error = oci_error($updateStmt);
                throw new Exception("Erro ao forçar atualização: " . $error['message']);
            }
            
            oci_commit($this->localConn);
            oci_free_statement($updateStmt);
            
            error_log("✅ FORÇADO: {$tableName} atualizada para {$total} registros");
            
            return [
                'success' => true,
                'table_name' => $tableName,
                'record_count' => $total,
                'message' => "Sync control atualizado com sucesso"
            ];
            
        } catch (Exception $e) {
            error_log("❌ ERRO ao forçar atualização: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // Workaround para tratar a tabela compl_pessoa_fisica que tem chave composta - 2 índices
    private function atualizarRegistroComposta($tableName, $row, $cd_pessoa, $nr_sequencia) {
        $updateCols = [];
        foreach ($row as $col => $val) {
            if ($col !== 'CD_PESSOA_FISICA' && $col !== 'NR_SEQUENCIA') {
                $updateCols[] = "{$col} = :{$col}";
            }
        }
        
        $sql = "UPDATE {$tableName} SET " . implode(', ', $updateCols) . 
            " WHERE CD_PESSOA_FISICA = :cd_pessoa AND NR_SEQUENCIA = :nr_sequencia";
        
        $stmt = oci_parse($this->localConn, $sql);
        
        foreach ($row as $col => $val) {
            if ($col !== 'CD_PESSOA_FISICA' && $col !== 'NR_SEQUENCIA') {
                oci_bind_by_name($stmt, ":{$col}", $row[$col]);
            }
        }
        oci_bind_by_name($stmt, ":cd_pessoa", $cd_pessoa);
        oci_bind_by_name($stmt, ":nr_sequencia", $nr_sequencia);
        
        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($stmt);
            throw new Exception("Erro ao atualizar: " . $error['message']);
        }
        
        oci_free_statement($stmt);
    }
}
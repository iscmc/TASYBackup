<?php
/**
 * Modelo base para todos os modelos do sistema
 *
 * Este arquivo faz parte do framework MVC Projeto Contingenciamento.
 * Centraliza as operações comuns de banco de dados.
 *
 * @category Framework
 * @package  Servidor de contingência ISCMC
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT, Apache
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-08-07
 * @maindev  Sergio Figueroa
 */

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {
    protected $conn;
    protected $tableName;
    
    public function __construct($tableName = null) {
        $this->tableName = $tableName;
        $this->connectToDatabase(); // Renomeei o método para ficar mais claro
    }

    protected function connectToDatabase() {
        $config = DatabaseConfig::$localDb; // Usando a conexão local por padrão
        
        // Verifica se as configurações necessárias existem
        if (!isset($config['host']) || !isset($config['port']) || !isset($config['sid'])) {
            throw new Exception("Configurações de banco de dados incompletas no arquivo database.php");
        }
        
        // Formato de conexão simplificado que funciona com Oracle XE
        $connectionString = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(Host=".$config['host'].")(Port=".$config['port']."))
                           (CONNECT_DATA=(SID=".$config['sid'].")))";
        
        $this->conn = oci_connect(
            $config['user'], 
            $config['pass'], 
            $connectionString,
            $config['charset'] ?? 'AL32UTF8'
        );
        
        if (!$this->conn) {
            $error = oci_error();
            throw new Exception("Falha na conexão com o banco de dados: " . 
                (is_array($error) ? $error['message'] : 'Erro desconhecido'));
        }
    }

    public function testConnection() {
        if (!$this->conn) {
            return 'inactive';
        }
        
        $stmt = oci_parse($this->conn, "SELECT 1 FROM DUAL");
        $result = oci_execute($stmt) ? 'active' : 'inactive';
        oci_free_statement($stmt);
        
        return $result;
    }

    public function getConnection() {
        return $this->conn;
    }

    protected function executeQuery($sql, $params = []) {
        $stmt = oci_parse($this->conn, $sql);
        
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $value);
        }
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            throw new Exception("Erro na execução da query: " . $error['message']);
        }
        
        return $stmt;
    }

    public function fetchAll($where = '', $params = [], $orderBy = '') {
        $sql = "SELECT * FROM {$this->tableName}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $stmt = $this->executeQuery($sql, $params);
        
        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }
        
        oci_free_statement($stmt);
        return $results;
    }

    public function fetchOne($where, $params = []) {
        $sql = "SELECT * FROM {$this->tableName} WHERE {$where} AND ROWNUM = 1";
        $stmt = $this->executeQuery($sql, $params);
        
        $result = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $result;
    }

    public function insert($data) {
        if (empty($data)) {
            return false;
        }
        
        $columns = implode(', ', array_keys($data));
        $values = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->tableName} ({$columns}) VALUES ({$values})";
        $stmt = $this->executeQuery($sql, $data);
        
        oci_commit($this->conn);
        oci_free_statement($stmt);
        
        return true;
    }

    public function update($data, $where, $params = []) {
        if (empty($data)) {
            return false;
        }
        
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$this->tableName} SET {$setClause} WHERE {$where}";
        $allParams = array_merge($data, $params);
        
        $stmt = $this->executeQuery($sql, $allParams);
        
        oci_commit($this->conn);
        oci_free_statement($stmt);
        
        return true;
    }

    public function delete($where, $params = []) {
        $sql = "DELETE FROM {$this->tableName} WHERE {$where}";
        $stmt = $this->executeQuery($sql, $params);
        
        oci_commit($this->conn);
        oci_free_statement($stmt);
        
        return true;
    }

    public function getPrimaryKeyColumn() {
        $sql = "SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND cols.table_name = UPPER(:table_name)";
        
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':table_name', $this->tableName);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_assoc($stmt);
            return $row['COLUMN_NAME'] ?? null;
        }
        
        return null;
    }

    public function tableExists() {
        $sql = "SELECT COUNT(*) FROM user_tables WHERE table_name = UPPER(:table_name)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':table_name', $this->tableName);
        
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return ($row['COUNT(*)'] > 0);
    }

    protected function formatOracleDate($dateString) {
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
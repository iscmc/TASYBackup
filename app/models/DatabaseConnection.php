<?php
/**
 * Gerenciador unificado de conexões Oracle
 */

require_once __DIR__ . '/../config/database.php';

class DatabaseConnection {
    private static $connections = [];
    
    public static function getConnection($type = 'local') {
        if (isset(self::$connections[$type]) && self::$connections[$type]) {
            return self::$connections[$type];
        }
        
        $config = ($type === 'local') ? DatabaseConfig::$localDb : DatabaseConfig::$tasyProdDb;
        
        // Configurações de ambiente
        putenv("NLS_LANG=BRAZILIAN PORTUGUESE_BRAZIL.AL32UTF8");
        putenv("ORA_SDTZ=America/Sao_Paulo");
        
        $connectionString = "//{$config['host']}:{$config['port']}/";
        $connectionString .= ($type === 'local') ? $config['sid'] : $config['service_name'];
        
        $conn = oci_connect(
            $config['user'],
            $config['pass'],
            $connectionString,
            $config['charset'],
            OCI_NO_AUTO_COMMIT
        );
        
        if (!$conn) {
            $e = oci_error();
            error_log("Erro de conexão Oracle ({$type}): " . $e['message']);
            throw new Exception("Falha na conexão com o banco de dados: " . $e['message']);
        }
        
        // Configurações de performance
        oci_set_client_identifier($conn, 'TASY_BACKUP_APP');
        oci_set_module_name($conn, ($type === 'local') ? 'LOCAL_DB' : 'PROD_DB');
        
        self::$connections[$type] = $conn;
        return $conn;
    }
    
    public static function closeAll() {
        foreach (self::$connections as $type => $conn) {
            if ($conn) {
                oci_close($conn);
                self::$connections[$type] = null;
            }
        }
    }
    
    public static function executeQuery($sql, $params = [], $type = 'local') {
        $conn = self::getConnection($type);
        $stmt = oci_parse($conn, $sql);
        
        if (!$stmt) {
            $e = oci_error($conn);
            throw new Exception("Erro no parse SQL: " . $e['message']);
        }
        
        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $params[$key]);
        }
        
        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmt);
            oci_free_statement($stmt);
            throw new Exception("Erro na execução SQL: " . $e['message']);
        }
        
        return $stmt;
    }
    
    public static function fetchAll($stmt) {
        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }
        oci_free_statement($stmt);
        return $results;
    }
}
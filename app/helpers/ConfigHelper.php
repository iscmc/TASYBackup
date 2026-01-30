<?php
/**
 * TASYBackup - Helper de Configurações
 * 
 * Gerencia configurações do sistema no banco de dados
 *
 * @category Helper
 * @package  TASYBackup
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-12-19
 */
class ConfigHelper {
    private $conn;
    
    public function __construct() {
        // Usar a conexão do DatabaseConfig
        require_once __DIR__ . '/../config/database.php';
        try {
            $this->conn = $this->getConnectionFromExistingMethods();
        } catch (Exception $e) {
            // Fallback: criar conexão direta
            $this->conn = $this->createLocalConnection();
        }
    }
    
    // Tenta obter conexão dos métodos existentes
    private function getConnectionFromExistingMethods() {
        if (method_exists('DatabaseConfig', 'getConnection')) {
            return DatabaseConfig::getConnection();
        }
        throw new Exception("Método de conexão não encontrado");
    }
    
    // Cria conexão direta (fallback)
    private function createLocalConnection() {
        // Usar as configurações do DatabaseConfig
        require_once __DIR__ . '/../config/database.php';
        
        $localDb = DatabaseConfig::$localDb;
        try {
            $tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$localDb['host']})(PORT={$localDb['port']}))(CONNECT_DATA=(SID={$localDb['sid']})))";
            
            $conn = oci_connect(
                $localDb['user'],
                $localDb['pass'],
                $tns,
                $localDb['charset']
            );
            
            if (!$conn) {
                $error = oci_error();
                throw new Exception("Erro ao conectar ao Oracle XE: " . $error['message']);
            }
            
            return $conn;
            
        } catch (Exception $e) {
            error_log("ConfigHelper connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Obtém o status atual do front-end
    public function getFrontendStatus() {
        try {
            $sql = "SELECT VALOR FROM CONFIG WHERE CHAVE = 'FRONTEND_ACTIVE'";
            $stmt = oci_parse($this->conn, $sql);
            
            if (!oci_execute($stmt)) {
                error_log("Erro ao buscar FRONTEND_ACTIVE: " . oci_error($stmt));
                return 'FALSE'; // Valor padrão seguro
            }
            
            $row = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            return $row ? strtoupper($row['VALOR']) : 'FALSE';
            
        } catch (Exception $e) {
            error_log("ConfigHelper getFrontendStatus error: " . $e->getMessage());
            return 'FALSE'; // Fail-safe: se houver erro, permite acesso
        }
    }
    
    /**
     * Define o status do front-end
     */
    public function setFrontendStatus($blocked) {
        try {
            $value = $blocked ? 'TRUE' : 'FALSE';
            $descricao = $blocked 
                ? 'Front-end bloqueado para manutenção - ' . date('d/m/Y H:i:s')
                : 'Front-end liberado para acesso - ' . date('d/m/Y H:i:s');
            
            $sql = "UPDATE CONFIG 
                    SET VALOR = :valor, 
                        DESCRICAO = :descricao,
                        DT_ATUALIZACAO = CURRENT_TIMESTAMP,
                        NM_USUARIO = 'TASYBACKUP'
                    WHERE CHAVE = 'FRONTEND_ACTIVE'";
            
            $stmt = oci_parse($this->conn, $sql);
            oci_bind_by_name($stmt, ':valor', $value);
            oci_bind_by_name($stmt, ':descricao', $descricao);
            
            if (!oci_execute($stmt)) {
                $error = oci_error($stmt);
                oci_free_statement($stmt);
                throw new Exception("Erro ao atualizar CONFIG: " . $error['message']);
            }
            
            $rowsAffected = oci_num_rows($stmt);
            oci_free_statement($stmt);
            
            // Se não atualizou nenhuma linha, inserir novo registro
            if ($rowsAffected === 0) {
                $this->insertFrontendConfig($value, $descricao);
            }
            
            oci_commit($this->conn);
            return true;
            
        } catch (Exception $e) {
            error_log("ConfigHelper setFrontendStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insere configuração se não existir
     * caso muito remoto de acontecer, mas já sabe como é
     */
    private function insertFrontendConfig($value, $descricao) {
        $sql = "INSERT INTO CONFIG (CHAVE, VALOR, DESCRICAO, NM_USUARIO) 
                VALUES ('FRONTEND_ACTIVE', :valor, :descricao, 'TASYBACKUP')";
        
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':valor', $value);
        oci_bind_by_name($stmt, ':descricao', $descricao);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            oci_free_statement($stmt);
            throw new Exception("Erro ao inserir CONFIG: " . $error['message']);
        }
        
        oci_free_statement($stmt);
        return true;
    }
    
    /**
     * Destrutor - fecha a conexão
     */
    public function __destruct() {
        if ($this->conn) {
            @oci_close($this->conn);
        }
    }
}
?>
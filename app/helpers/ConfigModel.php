<?php
/**
 * TASYBackup - Model de Configurações
 * 
 * Gerencia operações de leitura/escrita na tabela CONFIG
 *
 * @category Model
 * @package  TASYBackup
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-12-19
 */
class ConfigModel {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * Obtém o valor de FRONTEND_ACTIVE
     */
    public function getFrontendActiveStatus() {
        $sql = "SELECT VALOR as PARAM_VALUE FROM CONFIG WHERE CHAVE = 'FRONTEND_ACTIVE'";
        
        $stmt = oci_parse($this->db, $sql);
        
        if (!oci_execute($stmt)) {
            error_log("Erro ao buscar FRONTEND_ACTIVE: " . oci_error($stmt));
            return 'FALSE'; // Valor padrão seguro
        }
        
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $row ? strtoupper($row['PARAM_VALUE']) : 'FALSE';
    }
    
    /**
     * Atualiza o valor de FRONTEND_ACTIVE
     */
    public function updateFrontendActiveStatus($value, $usuario = 'TASYBACKUP') {
        if (!in_array($value, ['TRUE', 'FALSE'])) {
            throw new Exception("Valor inválido para FRONTEND_ACTIVE: " . $value);
        }
        
        $descricao = $value === 'TRUE' 
            ? 'Front-end bloqueado para manutenção - ' . date('d/m/Y H:i')
            : 'Front-end liberado para acesso - ' . date('d/m/Y H:i');
        
        $sql = "UPDATE CONFIG 
                SET VALOR = :valor, 
                    DESCRICAO = :descricao,
                    DT_ATUALIZACAO = CURRENT_TIMESTAMP,
                    NM_USUARIO = :usuario
                WHERE CHAVE = 'FRONTEND_ACTIVE'";
        
        $stmt = oci_parse($this->db, $sql);
        
        oci_bind_by_name($stmt, ':valor', $value);
        oci_bind_by_name($stmt, ':descricao', $descricao);
        oci_bind_by_name($stmt, ':usuario', $usuario);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            oci_free_statement($stmt);
            throw new Exception("Erro ao atualizar CONFIG: " . $error['message']);
        }
        
        $rowCount = oci_num_rows($stmt);
        oci_free_statement($stmt);
        
        // Se não afetou nenhuma linha, insere um novo registro
        if ($rowCount === 0) {
            $this->insertFrontendActiveStatus($value, $descricao, $usuario);
        }
        
        oci_commit($this->db);
        return true;
    }
    
    /**
     * Insere o registro se não existir
     */
    private function insertFrontendActiveStatus($value, $descricao, $usuario) {
        $sql = "INSERT INTO CONFIG (CHAVE, VALOR, DESCRICAO, NM_USUARIO) 
                VALUES ('FRONTEND_ACTIVE', :valor, :descricao, :usuario)";
        
        $stmt = oci_parse($this->db, $sql);
        
        oci_bind_by_name($stmt, ':valor', $value);
        oci_bind_by_name($stmt, ':descricao', $descricao);
        oci_bind_by_name($stmt, ':usuario', $usuario);
        
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            oci_free_statement($stmt);
            throw new Exception("Erro ao inserir CONFIG: " . $error['message']);
        }
        
        oci_free_statement($stmt);
        return true;
    }
    
    /**
     * Obtém todas as configurações
     */
    public function getAllConfigs() {
        $sql = "SELECT CHAVE, VALOR, DESCRICAO, 
                       TO_CHAR(DT_ATUALIZACAO, 'DD/MM/YYYY HH24:MI:SS') as DT_FORMATADA,
                       NM_USUARIO
                FROM CONFIG 
                ORDER BY CHAVE";
        
        $stmt = oci_parse($this->db, $sql);
        
        if (!oci_execute($stmt)) {
            error_log("Erro ao buscar configurações: " . oci_error($stmt));
            return [];
        }
        
        $configs = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $configs[] = $row;
        }
        
        oci_free_statement($stmt);
        return $configs;
    }
}
?>
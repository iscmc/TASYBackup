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

/*require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel {
    
    public function __construct() {
        parent::__construct('USUARIO');
    }

    // Métodos específicos para usuários
    public function getUserByUsername($username) {
        return $this->fetchOne("NM_USUARIO = :username", [':username' => $username]);
    }

    public function getActiveUsers() {
        return $this->fetchAll("CD_STATUS = 'A'", [], "NM_USUARIO");
    }
    
    public function authenticate($username, $password) {
        $query = "SELECT NM_USUARIO, DS_EMAIL FROM USUARIO 
                 WHERE NM_USUARIO = :username 
                 AND DS_SENHA = :password";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':username', $username);
        oci_bind_by_name($stmt, ':password', $password);
        
        if (!oci_execute($stmt)) {
            $e = oci_error($stmt);
            error_log("Erro na autenticação: " . $e['message']);
            return false;
        }
        
        $user = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $user ?: false;
    }
}*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DatabaseConnection.php';

class UserModel {
    public function authenticate($username, $password) {
        try {
            $query = "SELECT NM_USUARIO, DS_EMAIL, DS_SENHA 
                     FROM USUARIO 
                     WHERE NM_USUARIO = :username 
                     AND DS_SENHA = :password";
            
            $stmt = DatabaseConnection::executeQuery($query, [
                ':username' => $username,
                ':password' => $password
            ], 'local');
            
            $user = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            return $user ?: false;
            
        } catch (Exception $e) {
            error_log("Erro de autenticação: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserInfo($username) {
        try {
            $query = "SELECT NM_USUARIO, DS_EMAIL, NM_FUNCIONARIO 
                     FROM USUARIO 
                     WHERE NM_USUARIO = :username";
            
            $stmt = DatabaseConnection::executeQuery($query, [
                ':username' => $username
            ], 'local');
            
            $user = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            return $user ?: false;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar informações do usuário: " . $e->getMessage());
            return false;
        }
    }
}
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

require_once __DIR__ . '/../models/BackupModel.php';
require_once __DIR__ . '/../models/SyncModel.php';
// REMOVER requires de UserModel e PacienteModel que não são mais necessários para auth

class HomeController {
    private $backupModel;
    private $syncModel;
    
    public function __construct() {
        $this->backupModel = new BackupModel();
        $this->syncModel = new SyncModel();
        // REMOVER inicialização de UserModel e PacienteModel
    }
    
    public function index() {
        // REMOVER verificação de autenticação
        try {
            $syncStatus = $this->syncModel->getSyncStatus();
            $connectionStatus = $this->backupModel->testConnections();
            $systemInfo = $this->syncModel->getSystemInfo();
            
            include __DIR__ . '/../views/home.php';
        } catch (Exception $e) {
            // Fallback para caso de falha
            $connectionStatus = [
                'source' => 'error',
                'local' => 'error'
            ];
            $systemInfo = [];
            $syncStatus = [];
            
            include __DIR__ . '/../views/home.php';
        }   
    }

    // REMOVER métodos de login e logout
    // public function login() { ... }
    // public function logout() { ... }
    // public function searchPaciente() { ... }

    public function forceSync() {
        // REMOVER verificação de autenticação
        try {
            $table = $_GET['table'] ?? null;
            if ($table) {
                $result = $this->syncModel->forceTableSync($table);
                $_SESSION['message'] = "Sincronização forçada para $table concluída. ".$result['count']." registros atualizados.";
            } else {
                $results = $this->syncModel->forceFullSync();
                $total = array_sum(array_column($results, 'count'));
                $_SESSION['message'] = "Sincronização completa forçada. $total registros atualizados no total.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erro durante sincronização: ".$e->getMessage();
        }
        
        header('Location: /TASYBackup/');
    }
    
    public function viewLogs() {
        // REMOVER verificação de autenticação
        $logs = $this->syncModel->getRecentLogs(50);
        include __DIR__ . '/../views/logs.php';
    }
}
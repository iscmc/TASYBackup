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

require_once __DIR__ . '/../models/BackupModel.php';

class BackupController {
    private $model;
    
    public function __construct() {
        $this->model = new BackupModel();
    }
    
    public function dashboard() {
        $syncStatus = $this->model->getSyncStatus();
        $connectionStatus = $this->model->testConnections();
        
        include __DIR__ . '/../views/dashboard.php';
    }
    
    public function updateSettings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar e atualizar configurações
            $this->model->updateSyncInterval($_POST['interval']);
            header('Location: /?action=dashboard&msg=updated');
        }
    }
}
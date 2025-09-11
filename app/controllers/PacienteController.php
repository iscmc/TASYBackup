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
require_once __DIR__ . '/../models/PacienteModel.php';

class PacienteController {
    private $pacienteModel;
    
    public function __construct() {
        $this->pacienteModel = new PacienteModel();
    }
    
    public function search() {
        if (!isset($_SESSION['usuario_logado'])) {
            header('Location: /TASYBackup/login');
            exit;
        }
        
        $results = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $criterioBusca = $_POST['searchTerm'];
            $results = $this->PacienteModel->searchPacientes($criterioBusca);
        }
        
        include __DIR__ . '/../views/Paciente_search.php';
    }
}
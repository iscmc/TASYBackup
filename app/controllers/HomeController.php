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
require_once __DIR__ . '/../models/SyncModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/PacienteModel.php';

class HomeController {
    private $backupModel;
    private $syncModel;
    private $userModel;
    private $pacienteModel;
    
    public function __construct() {
        $this->initializeSession();
        $this->backupModel = new BackupModel();
        $this->syncModel = new SyncModel();
        $this->userModel = new UserModel();
        $this->pacienteModel = new PacienteModel();
    }

    /**
     * Inicializa a sessão se não estiver ativa
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Página inicial/dashboard
     */
    public function index() {
        $this->requireAuthentication();
        
        try {
            $data = [
                'syncStatus' => $this->syncModel->getSyncStatus(),
                'connectionStatus' => $this->backupModel->testConnections(),
                'systemInfo' => $this->syncModel->getSystemInfo()
            ];
            
            $this->loadView('home', $data);
        } catch (Exception $e) {
            $this->handleError($e, 'Erro ao carregar dashboard');
            $this->loadView('home', [
                'connectionStatus' => ['source' => 'error', 'local' => 'error'],
                'systemInfo' => [],
                'syncStatus' => []
            ]);
        }
    }

    /**
     * Página de login
     */
    public function login() {
        // Se já estiver logado, redireciona
        if ($this->isAuthenticated()) {
            $this->redirect('/');
        }

        $error = null;
        
        // Processa o formulário de login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = $this->handleLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
        }
        
        $this->loadView('auth/login', ['error' => $error]);
    }

    /**
     * Processa o login
     */
    private function handleLogin($username, $password) {
        try {
            $user = $this->userModel->authenticate($username, $password);
            
            if ($user) {
                $_SESSION['usuario_logado'] = $user;
                $this->redirect('/');
                return null;
            }
            
            return "Usuário ou senha inválidos";
        } catch (Exception $e) {
            error_log("Erro de autenticação: " . $e->getMessage());
            return "Erro durante o login. Tente novamente.";
        }
    }

    /**
     * Logout do sistema
     */
    public function logout() {
        session_destroy();
        $this->redirect('/login');
    }

    /**
     * Busca de pacientes
     */
    public function searchPaciente() {
        $this->requireAuthentication();

        $results = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $results = $this->pacienteModel->searchPaciente($_POST['searchTerm'] ?? '');
        }
        
        $this->loadView('busca_paciente', ['results' => $results]);
    }

    /**
     * Força sincronização
     */
    public function forceSync() {
        $this->requireAuthentication();

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
        
        $this->redirect('/');
    }

    /**
     * Visualização de logs
     */
    public function viewLogs() {
        $this->requireAuthentication();
        $logs = $this->syncModel->getRecentLogs(50);
        $this->loadView('logs', ['logs' => $logs]);
    }

    /**
     * Métodos auxiliares
     */
    
    private function isAuthenticated() {
        return isset($_SESSION['usuario_logado']);
    }

    private function requireAuthentication() {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    private function redirect($path) {
        $url = rtrim(BASE_URL, '/') . $path;
        header('Location: ' . $url);
        exit;
    }

    private function loadView($view, $data = []) {
        extract($data);
        $viewPath = TEMPLATE_PATH . $view . '.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            throw new Exception("View não encontrada: " . $viewPath);
        }
    }

    private function handleError(Exception $e, $message = '') {
        error_log($message . ': ' . $e->getMessage());
        $_SESSION['error'] = $message;
    }
}
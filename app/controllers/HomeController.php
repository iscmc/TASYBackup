<?php
/**
 * TASYBackup - Controller da Home
 * 
 * Controlador principal do dashboard TASYBackup
 *
 * @category Controller
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
require_once __DIR__ . '/../helpers/ConfigHelper.php';

class HomeController {
    private $backupModel;
    private $syncModel;
    private $configHelper;
    
    public function __construct() {
        $this->backupModel = new BackupModel();
        $this->syncModel = new SyncModel();
        $this->configHelper = new ConfigHelper(); // inicaliza ConfigHelper
    }
    
    public function index() {
        try {
            $syncStatus = $this->syncModel->getSyncStatus();
            $connectionStatus = $this->backupModel->testConnections();
            $systemInfo = $this->syncModel->getSystemInfo();
            // OBTER STATUS DO FRONT-END
            $frontendStatus = $this->configHelper->getFrontendStatus();            
            include __DIR__ . '/../views/home.php';
        } catch (Exception $e) {
            // Fallback genérico  para caso de falha
            $connectionStatus = [
                'source' => 'error',
                'local' => 'error'
            ];
            $systemInfo = [];
            $syncStatus = [];
            $frontendStatus = 'FALSE'; // Valor padrão seguro
            
            include __DIR__ . '/../views/home.php';
        }   
    }

    // NOVO MÉTODO: Atualizar configuração do front-end
    public function updateFrontendAccess() {
        try {
            // Verificar se é POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = "Método não permitido";
                header('Location: /TASYBackup/');
                exit;
            }
            
            // Validar e obter valor
            $frontendActive = $_POST['frontend_active'] ?? 'FALSE';
            if (!in_array($frontendActive, ['TRUE', 'FALSE'])) {
                $_SESSION['error'] = "Valor inválido para configuração";
                header('Location: /TASYBackup/');
                exit;
            }
            
            // Atualizar no banco
            $success = $this->configHelper->setFrontendStatus($frontendActive === 'TRUE');
            
            if ($success) {
                $_SESSION['message'] = $frontendActive === 'TRUE' 
                    ? "Front-end bloqueado com sucesso! Usuários verão a página 'Coming Soon'." 
                    : "Front-end liberado com sucesso! Acesso normal restaurado.";
            } else {
                $_SESSION['error'] = "Erro ao atualizar configuração do front-end.";
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erro: " . $e->getMessage();
        }
        
        // Redirecionar de volta para a home
        header('Location: /TASYBackup/');
        exit;
    }
    
    public function forceSync() {
        try {
            $table = $_GET['table'] ?? null;
            
            // DIAGNÓSTICO ANTES DA SINCRONIZAÇÃO
            if ($table && strpos($table, 'CPOE_') === 0) {
                $diagnostico = $this->backupModel->diagnosticoEmergencial($table, 10);
                error_log("Diagnóstico pré-sincronização: " . count($diagnostico) . " problemas potenciais");
            }
            
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
        $logs = $this->syncModel->getRecentLogs(50);
        include __DIR__ . '/../views/logs.php';
    }

    public function testFetch() {
        try {
            $table = $_GET['table'] ?? 'CPOE_GASOTERAPIA';
            
            // Usar o método público de diagnóstico
            $resultado = $this->backupModel->diagnosticarTabela($table);
            
            if ($resultado['status'] === 'success') {
                echo "<h3>Diagnóstico da Tabela: {$table}</h3>";
                echo "Total de registros encontrados: " . $resultado['total_registros'] . "<br>";
                echo "Registros válidos: " . $resultado['registros_validos'] . "<br>";
                echo "Registros com chave NULL/vazia: " . $resultado['registros_null'] . "<br>";
                echo "Registros inválidos: " . $resultado['registros_invalidos'] . "<br>";
                echo "Chave primária: " . $resultado['chave_primaria'] . "<br>";
                
                if ($resultado['registros_null'] > 0) {
                    echo "<p style='color: red; font-weight: bold;'>⚠️ ATENÇÃO: Existem registros com chave primária NULL!</p>";
                }
            } else {
                echo "ERRO: " . $resultado['message'];
            }
            
        } catch (Exception $e) {
            echo "ERRO: " . $e->getMessage();
        }
    }

    public function testSync() {
        try {
            $table = $_GET['table'] ?? 'CPOE_GASOTERAPIA';
            
            echo "<h3>Teste de Sincronização: {$table}</h3>";
            echo "Iniciando sincronização...<br>";
            echo "Verifique os LOGS para ver o debug detalhado!<br>";
            echo "<br><strong>Acompanhe o processamento em tempo real nos logs do servidor!</strong><br>";
            
            // Executar sincronização
            $result = $this->backupModel->insertDataToLocal($table);
            
            echo "<h4>Resultado:</h4>";
            echo "Sucesso: " . ($result['success'] ? 'SIM' : 'NÃO') . "<br>";
            echo "Mensagem: " . $result['message'] . "<br>";
            echo "Registros processados: " . $result['records_processed'] . "<br>";
            echo "Inseridos: " . $result['inserted'] . "<br>";
            echo "Atualizados: " . $result['updated'] . "<br>";
            echo "Erros: " . $result['errors'] . "<br>";
            echo "Registros inválidos: " . $result['invalid_records'] . "<br>";
            
        } catch (Exception $e) {
            echo "ERRO: " . $e->getMessage();
        }
    }
}
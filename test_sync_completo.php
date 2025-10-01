<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/SyncModel.php';

try {
    echo "=== TESTE DE SINCRONIZAÇÃO COMPLETA CPOE_DIETA ===\n";
    
    $syncModel = new SyncModel();
    
    echo "1. Executando forceTableSync...\n";
    $result = $syncModel->forceTableSync('CPOE_DIETA');
    
    echo "2. Resultado:\n";
    echo "   Tabela: " . $result['table'] . "\n";
    echo "   Registros: " . $result['count'] . "\n";
    echo "   Status: " . $result['status'] . "\n";
    
    if (isset($result['message'])) {
        echo "   Mensagem: " . $result['message'] . "\n";
    }
    
    echo "\n3. Verificando status atual...\n";
    $status = $syncModel->getSyncStatus();
    foreach ($status as $table) {
        if ($table['TABLE_NAME'] === 'CPOE_DIETA') {
            echo "   CPOE_DIETA - Último Sync: " . ($table['LAST_SYNC'] ?? 'NUNCA') . "\n";
            echo "   CPOE_DIETA - Total Registros: " . ($table['RECORD_COUNT'] ?? '0') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
}
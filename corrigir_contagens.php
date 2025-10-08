<?php
// corrigir_contagens.php
require_once 'app/config/database.php';
require_once 'app/models/BackupModel.php';

echo "=== INICIANDO CORREÇÃO DE CONTAGENS ===\n";

try {
    $backupModel = new BackupModel();
    $resultados = $backupModel->corrigirContagensImediatamente();
    
    echo "✅ CORREÇÃO CONCLUÍDA!\n";
    echo "Resultados:\n";
    
    foreach ($resultados as $tabela => $resultado) {
        if ($resultado['success']) {
            echo "✅ {$tabela}: {$resultado['record_count']} registros\n";
        } else {
            echo "❌ {$tabela}: ERRO - {$resultado['message']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}

echo "=== FIM DA CORREÇÃO ===\n";
?>
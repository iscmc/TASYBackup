<?php
// testar_sync_control.php
require_once 'app/config/database.php';
require_once 'app/models/BackupModel.php';

echo "=== TESTE DO SYNC CONTROL ===\n";

try {
    $backupModel = new BackupModel();
    
    // 1. Verificar estado atual
    echo "\n📊 ESTADO ATUAL:\n";
    $estadoAtual = $backupModel->verificarEstadoSyncControl();
    foreach ($estadoAtual as $tabela) {
        echo "{$tabela['table_name']}: {$tabela['record_count']} registros (sync: {$tabela['last_sync']})\n";
    }
    
    // 2. Testar com uma tabela específica
    $tabelaTeste = 'USUARIO'; // Altere para a tabela que você quer testar
    
    echo "\n🔄 TESTANDO TABELA: {$tabelaTeste}\n";
    
    // Forçar atualização
    $resultado = $backupModel->forcarAtualizacaoSyncControl($tabelaTeste);
    
    if ($resultado['success']) {
        echo "✅ SUCESSO: {$resultado['message']}\n";
    } else {
        echo "❌ ERRO: {$resultado['message']}\n";
    }
    
    // 3. Verificar estado após correção
    echo "\n📊 ESTADO APÓS CORREÇÃO:\n";
    $estadoFinal = $backupModel->verificarEstadoSyncControl($tabelaTeste);
    foreach ($estadoFinal as $tabela) {
        echo "{$tabela['table_name']}: {$tabela['record_count']} registros (sync: {$tabela['last_sync']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>
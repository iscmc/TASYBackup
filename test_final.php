<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== TESTE FINAL CPOE_DIETA ===\n";
    
    $model = new BackupModel();
    
    // Teste 1: Conexão
    echo "1. Testando conexões...\n";
    $connStatus = $model->testConnections();
    echo "   Fonte: {$connStatus['source']}, Local: {$connStatus['local']}\n";
    
    // Teste 2: Método direto
    echo "2. Executando fetchNewRecords...\n";
    $registros = $model->fetchNewRecords('CPOE_DIETA');
    echo "   ✓ Sucesso! Encontrados: " . count($registros) . " registros\n";
    
    // Teste 3: Mostrar alguns registros
    if (count($registros) > 0) {
        echo "3. Primeiro registro:\n";
        echo "   NR_SEQUENCIA: " . ($registros[0]['NR_SEQUENCIA'] ?? 'N/A') . "\n";
        echo "   DT_ATUALIZACAO: " . ($registros[0]['DT_ATUALIZACAO'] ?? 'N/A') . "\n";
    }
    
    echo "\n=== TESTE CONCLUÍDO ===\n";
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    
    // Mostra o trace completo
    echo "Trace completo:\n";
    $trace = $e->getTrace();
    foreach ($trace as $i => $call) {
        echo "  #{$i} {$call['file']}({$call['line']}): {$call['function']}\n";
    }
}
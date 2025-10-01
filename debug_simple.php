<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== DEBUG SIMPLES CPOE_DIETA ===\n";
    
    // Teste básico de conexão
    $model = new BackupModel();
    echo "✓ BackupModel instanciado\n";
    
    // Teste de conexões
    $connections = $model->testConnections();
    echo "Conexão fonte: " . $connections['source'] . "\n";
    echo "Conexão local: " . $connections['local'] . "\n";
    
    // Teste direto da query que funciona no SQL Developer
    echo "\n=== TESTE DIRETO DA QUERY ===\n";
    $sql = "SELECT COUNT(*) as total FROM TASY.CPOE_DIETA WHERE DT_ATUALIZACAO >= TO_TIMESTAMP('26-Sep-2025 17:07:19', 'DD-MON-YYYY HH24:MI:SS')";
    echo "SQL: " . $sql . "\n";
    
    $stmt = oci_parse($model->getSourceConnection(), $sql);
    if ($stmt && oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        echo "✓ Query executada com sucesso: " . $row['TOTAL'] . " registros\n";
        oci_free_statement($stmt);
    } else {
        $error = oci_error($model->getSourceConnection());
        echo "✗ Erro na query: " . $error['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
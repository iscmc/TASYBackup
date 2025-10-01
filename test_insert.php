<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== TESTE DE INSERÇÃO CPOE_DIETA ===<br>";
    
    $model = new BackupModel();
    
    // Primeiro, busca alguns registros
    echo "1. Buscando registros...<br>";
    $registros = $model->fetchNewRecords('CPOE_DIETA');
    echo "   Registros encontrados: " . count($registros) . "<br>";
    
    if (count($registros) > 0) {
        // Pega apenas os 5 primeiros para teste
        $testRecords = array_slice($registros, 0, 5);
        echo "2. Testando inserção de " . count($testRecords) . " registros...<br>";
        
        // Verifica se a tabela existe no local
        echo "3. Verificando tabela local...<br>";
        $checkSql = "SELECT COUNT(*) as total FROM USER_TABLES WHERE TABLE_NAME = 'CPOE_DIETA'";
        $stmt = oci_parse($model->getLocalConnection(), $checkSql);
        oci_execute($stmt);
        $tableExists = oci_fetch_assoc($stmt)['TOTAL'] > 0;
        oci_free_statement($stmt);
        
        echo "   Tabela local existe: " . ($tableExists ? 'SIM' : 'NÃO') . "<br>";
        
        if ($tableExists) {
            // Tenta inserir
            $model->insertDataToLocal('CPOE_DIETA', $testRecords);
            echo "   ✓ Inserção realizada com sucesso!<br>";
            
            // Verifica se os dados foram inseridos
            $countSql = "SELECT COUNT(*) as total FROM CPOE_DIETA";
            $stmt = oci_parse($model->getLocalConnection(), $countSql);
            oci_execute($stmt);
            $totalLocal = oci_fetch_assoc($stmt)['TOTAL'];
            oci_free_statement($stmt);
            
            echo "   Total de registros na tabela local: " . $totalLocal . "<br>";
        } else {
            echo "   ✗ Tabela CPOE_DIETA não existe no banco local<br>";
        }
    }
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "<br>";
}
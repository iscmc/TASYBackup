<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== TESTE DO MERGE CPOE_DIETA ===<br>";
    
    $model = new BackupModel();
    
    // Busca alguns registros
    echo "1. Buscando registros...<br>";
    $registros = $model->fetchNewRecords('CPOE_DIETA');
    echo "   Registros encontrados: " . count($registros) . "<br>";
    
    if (count($registros) > 0) {
        // Pega apenas 3 registros para teste
        $testRecords = array_slice($registros, 0, 3);
        
        echo "2. Primeira inserção (3 registros)...<br>";
        $model->insertDataToLocal('CPOE_DIETA', $testRecords);
        echo "   ✓ Primeira inserção concluída<br>";
        
        // Verifica quantos registros existem
        $countSql = "SELECT COUNT(*) as total FROM CPOE_DIETA";
        $stmt = oci_parse($model->getLocalConnection(), $countSql);
        oci_execute($stmt);
        $total1 = oci_fetch_assoc($stmt)['TOTAL'];
        oci_free_statement($stmt);
        echo "   Total após primeira inserção: " . $total1 . "<br>";
        
        echo "3. Segunda inserção (mesmos 3 registros)...<br>";
        $model->insertDataToLocal('CPOE_DIETA', $testRecords);
        echo "   ✓ Segunda inserção concluída<br>";
        
        // Verifica novamente
        $stmt = oci_parse($model->getLocalConnection(), $countSql);
        oci_execute($stmt);
        $total2 = oci_fetch_assoc($stmt)['TOTAL'];
        oci_free_statement($stmt);
        echo "   Total após segunda inserção: " . $total2 . "<br>";
        
        if ($total1 === $total2) {
            echo "   ✅ PERFEITO! MERGE funcionando - não houve duplicatas<br>";
        } else {
            echo "   ❌ PROBLEMA! Foram criadas " . ($total2 - $total1) . " duplicatas<br>";
        }
    }
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "<br>";
}
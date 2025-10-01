<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== TESTE SIMPLIFICADO - TODAS AS TABELAS ===<br>";
    
    $model = new BackupModel();
    $results = $model->syncAllTables();
    
    foreach ($results as $table => $result) {
        echo "<br><strong>{$table}:</strong> ";
        if ($result['success']) {
            echo "✅ " . $result['message'];
            if (isset($result['records_processed'])) {
                echo " ({$result['records_processed']} registros)";
            }
        } else {
            echo "❌ " . $result['message'];
        }
        echo "<br>";
    }
    
    echo "<br>=== FIM DO TESTE ===";
    
} catch (Exception $e) {
    echo "✗ ERRO GERAL: " . $e->getMessage();
}
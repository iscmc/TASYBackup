<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';

try {
    echo "=== TESTE ESPECÍFICO CPOE_DIETA ===<br>";
    $model = new BackupModel();

    // Debug da configuração
    $debugInfo = DatabaseConfig::debugTableInfo('CPOE_DIETA');
    echo "Configuração: <br>";
    print_r($debugInfo);
    echo "<br>";

    // Testa a busca de registros
    echo "Buscando registros...<br>";
    $registros = $model->fetchNewRecords('CPOE_DIETA');
    
    echo "Registros encontrados: " . count($registros) . "\n";
    exit();
    if (count($registros) > 0) {
        echo "Primeiros 3 registros:\n";
        for ($i = 0; $i < min(3, count($registros)); $i++) {
            echo "Registro " . ($i+1) . ":\n";
            print_r($registros[$i]);
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
}
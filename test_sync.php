<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/BackupModel.php';
require_once __DIR__ . '/app/models/SyncModel.php';

try {
    echo "Testando conexões...\n";
    
    $syncModel = new SyncModel();
    $connections = $syncModel->testConnections();
    
    echo "Conexão fonte: " . $connections['source'] . "<br>";
    echo "Conexão local: " . $connections['local'] . "<br>";
    
    echo "Buscando status de sync...<br>";
    $syncStatus = $syncModel->getSyncStatus();
    
    echo "Status encontrado: " . count($syncStatus) . " tabelas<br>";
    print_r($syncStatus);
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "<br>";
}
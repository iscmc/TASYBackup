<?php
/**
 * Servidor de contingência ISCMC Off frid
 *
 * Este arquivo faz parte do framework MVC Projeto Contingenciamento.
 *
 * @category Framework
 * @package  Servidor de contingência ISCMC
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT, Apache
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-07-01
 * @maindev  Sergio Figueroa
 */

/**
 * Script para verificar o ambiente e configurações
 */

echo "Verificando ambiente TASY Backup...<br>\n";

// Verificar extensões
$extensions = ['oci8', 'pdo_oci', 'mbstring', 'curl'];
foreach ($extensions as $ext) {
    echo extension_loaded($ext) ? "✓ {$ext}<br>\n" : "✗ {$ext} (FALTANDO)<br>\n";
}

// Verificar configurações Oracle
echo "NLS_LANG: " . (getenv('NLS_LANG') ?: 'Não configurado') . "<br>\n";
echo "ORA_SDTZ: " . (getenv('ORA_SDTZ') ?: 'Não configurado') . "<br>\n";

// Testar conexões
try {
    require_once __DIR__ . '/app/config/database.php';
    require_once __DIR__ . '/app/models/DatabaseConnection.php';
    
    // Testar conexão local
    $localConn = DatabaseConnection::getConnection('local');
    echo "✓ Conexão local: OK<br>\n";
    
    // Testar consulta simples
    $stmt = oci_parse($localConn, "SELECT 1 FROM DUAL");
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    echo "✓ Consulta teste: OK<br>\n";
    oci_free_statement($stmt);
    
} catch (Exception $e) {
    echo "✗ Erro na conexão: " . $e->getMessage() . "<br>\n";
}

echo "Verificação concluída.<br>\n";
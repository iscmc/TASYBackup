<?php
/**
 * Verificador de Extensões OCI8
 */

echo "<h2>Verificação de Extensões PHP</h2>";

// Verificar extensões Oracle
$oracle_extensions = [
    'oci8' => 'OCI8 (Oracle)',
    'pdo_oci' => 'PDO_OCI (Oracle PDO)'
];

foreach ($oracle_extensions as $ext => $name) {
    if (extension_loaded($ext)) {
        echo "✅ <strong>$name</strong>: CARREGADA<br>";
        echo "&nbsp;&nbsp;&nbsp;Versão: " . phpversion($ext) . "<br>";
    } else {
        echo "❌ <strong>$name</strong>: NÃO CARREGADA<br>";
    }
    echo "<br>";
}

// Verificar funções OCI8
$oci_functions = ['oci_connect', 'oci_parse', 'oci_execute', 'oci_fetch_assoc'];
echo "<h3>Funções OCI8 Disponíveis:</h3>";
foreach ($oci_functions as $func) {
    echo function_exists($func) ? "✅ $func<br>" : "❌ $func<br>";
}

// Verificar configuração
echo "<h3>Configuração PHP:</h3>";
echo "extension_dir: " . ini_get('extension_dir') . "<br>";
echo "php.ini carregado: " . php_ini_loaded_file() . "<br>";

// Testar conexão se OCI8 estiver carregada
if (extension_loaded('oci8')) {
    echo "<h3>Teste de Conexão Oracle:</h3>";
    try {
        $conn = oci_connect('SYSTEM', 'K@t7y317', 'localhost/XE', 'AL32UTF8');
        if ($conn) {
            echo "✅ Conexão Oracle bem-sucedida!<br>";
            
            $stmt = oci_parse($conn, "SELECT SYSDATE as data_atual FROM DUAL");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            echo "✅ Data do Oracle: " . $row['DATA_ATUAL'] . "<br>";
            
            oci_close($conn);
        } else {
            $e = oci_error();
            echo "❌ Erro na conexão: " . $e['message'] . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
}
?>
<?php
require __DIR__ . '/app/config/database.php';

$table = strtoupper($argv[1] ?? '');
if ($table === '') {
    fwrite(STDERR, "Uso: php debug_constraints.php TABELA\n");
    exit(1);
}

$local = DatabaseConfig::$localDb;
$tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$local['host']})(PORT={$local['port']}))(CONNECT_DATA=(SID={$local['sid']})))";
$conn = oci_connect($local['user'], $local['pass'], $tns, $local['charset']);

$sql = "SELECT c.constraint_name, c.constraint_type, col.column_name, col.position
        FROM user_constraints c
        JOIN user_cons_columns col ON c.constraint_name = col.constraint_name
        WHERE c.table_name = :table_name
        ORDER BY c.constraint_name, col.position";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':table_name', $table);
oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

oci_free_statement($stmt);
oci_close($conn);

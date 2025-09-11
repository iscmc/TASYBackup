<?php
/**
 * Servidor de contingência ISCMC Off Grid
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup TASY EMR</title>
    <style>
        .status-active { color: green; }
        .status-inactive { color: red; }
    </style>
</head>
<body>
    <h1>Controle de Backup TASY EMR</h1>
    
    <div class="connection-status">
        <h2>Status das Conexões</h2>
        <p>Banco Cloud: <span class="status-<?= $connectionStatus['source'] ?>">
            <?= strtoupper($connectionStatus['source']) ?></span></p>
        <p>Banco Local: <span class="status-<?= $connectionStatus['local'] ?>">
            <?= strtoupper($connectionStatus['local']) ?></span></p>
    </div>
    
    <div class="sync-status">
        <h2>Últimas Sincronizações</h2>
        <table border="1">
            <tr>
                <th>Tabela</th>
                <th>Última Sincronização</th>
                <th>Registros</th>
                <th>Status</th>
            </tr>
            <?php foreach ($syncStatus as $table): ?>
            <tr>
                <td><?= htmlspecialchars($table['table_name']) ?></td>
                <td><?= $table['last_sync'] ?: 'Nunca' ?></td>
                <td><?= $table['record_count'] ?></td>
                <td><?= $table['status'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="settings">
        <h2>Configurações</h2>
        <form method="post" action="?action=updateSettings">
            <label>Intervalo de Sincronização (minutos):</label>
            <input type="number" name="interval" value="15" min="1">
            <button type="submit">Salvar</button>
        </form>
    </div>
</body>
</html>
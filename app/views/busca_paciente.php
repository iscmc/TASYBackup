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
    <title>Consulta de Pacientes - TASY Backup</title>
    <link rel="stylesheet" href="/TASYBackup/assets/css/styles.css">
</head>
<body>
    <header>
        <h1>Consulta de Pacientes</h1>
        <a href="/TASYBackup/logout">Sair</a>
    </header>
    
    <div class="search-container">
        <form method="POST">
            <input type="text" name="searchTerm" placeholder="Nome do paciente...">
            <button type="submit">Buscar</button>
        </form>
    </div>
    
    <?php if (!empty($results)): ?>
    <div class="results-container">
        <h2>Resultados</h2>
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Procedimento</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['NM_PACIENTE']) ?></td>
                    <td><?= htmlspecialchars($row['DS_PROCEDIMENTO']) ?></td>
                    <td><?= htmlspecialchars($row['DT_PROCEDIMENTO']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</body>
</html>
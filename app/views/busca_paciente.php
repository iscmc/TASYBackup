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
 * */
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
        <a href="/TASYBackup/" class="btn">Voltar ao Dashboard</a> <!-- Alterado de Sair para Voltar -->
    </header>
    
    <div class="search-container">
        <form method="POST">
            <input type="text" name="searchTerm" placeholder="Nome do paciente..." required>
            <button type="submit">Buscar</button>
        </form>
    </div>
    
    <?php if (!empty($results)): ?>
    <div class="results-container">
        <h2>Resultados (<?= count($results) ?> registros)</h2>
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Tabela Origem</th>
                    <th>Última Atualização</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['NM_PACIENTE'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['SOURCE_TABLE'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['DT_ATUALIZACAO'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="no-results">
        <p>Nenhum paciente encontrado com o termo "<?= htmlspecialchars($_POST['searchTerm']) ?>".</p>
    </div>
    <?php endif; ?>
</body>
</html>
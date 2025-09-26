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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup TASY EMR - Dashboard</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>assets/img/icone-site.png">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #2c3e50;
        }
        .status-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .status-active {
            color: #27ae60;
            font-weight: bold;
        }
        .status-inactive {
            color: #e74c3c;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .info-card h3 {
            margin-top: 0;
            color: #3498db;
        }
        .status-error {
            color: #ff0000;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Backup TASY EMR - Dashboard</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="system-info">
            <div class="info-card">
                <h3>Banco de Dados</h3>
                <p>
                    <strong>Tasy Cloud:</strong>
                    <span class="status-<?= strtolower($connectionStatus['source']) ?>">
                        <?php echo strtoupper($connectionStatus['source']); ?>
                    </span>
                </p>
                <p><strong>Local:</strong>
                    <span class="status-<?= strtolower($connectionStatus['local']) ?>">
                        <?php echo strtoupper($connectionStatus['local']); ?>
    </span>
                </p>
            </div>
            
            <div class="info-card">
                <h3>Última Sincronização</h3>
                <p><?= $systemInfo['LAST_SYNC_OVERALL'] ?: 'Nunca' ?></p>
            </div>
            
            <div class="info-card">
                <h3>Tabelas</h3>
                <p><strong>Ativas:</strong> <?= $systemInfo['TABLES_ACTIVE'] ?></p>
                <p><strong>Total:</strong> <?= $systemInfo['TABLES_CONFIGURED'] ?></p>
            </div>
            
            <div class="info-card">
                <h3>Registros</h3>
                <p><?php //echo $systemInfo['TOTAL_RECORDS']; ?>
                <p><?= $systemInfo['TOTAL_RECORDS'] !== null ? number_format($systemInfo['TOTAL_RECORDS'], 0, ',', '.') : '0' ?></p>
            </div>
        </div>
        
        <div class="status-box">
            <h2>Ações Rápidas</h2>
            <a href="?action=forceSync" class="btn">Forçar Sincronização Completa</a>
            <a href="?action=viewLogs" class="btn">Ver Logs</a>
        </div>
        
        <div class="status-box">
            <h2>Status das Tabelas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Última Sincronização</th>
                        <th>Registros</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($syncStatus as $table): ?>
                    <tr>
                        <td><?= htmlspecialchars($table['TABLE_NAME']) ?></td>
                        <td><?= $table['LAST_SYNC'] ?: 'Nunca' ?></td>
                        <td><?= number_format($table['RECORD_COUNT'], 0, ',', '.') ?></td>
                        <td><span class="status-<?= strtolower($table['STATUS']) ?>">
                            <?= $table['STATUS'] ?></span></td>
                        <td>
                            <a href="?action=forceSync&table=<?= $table['TABLE_NAME'] ?>" class="btn">Sincronizar</a>
                            <?php if ($table['STATUS'] == 'ACTIVE'): ?>
                                <a href="#" class="btn btn-danger">Desativar</a>
                            <?php else: ?>
                                <a href="#" class="btn">Ativar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php 
            /* REMOVER seção de autenticação
            <?php if (isset($_SESSION['user'])): ?>
            <div class="user-actions">
                <h3>Consultas</h3>
                <a href="/TASYBackup/patients" class="btn">Consultar Pacientes</a>
                <a href="/TASYBackup/logout" class="btn">Sair</a>
            </div>
            <?php endif; ?>
            */ ?>

            <!-- Adicionar acesso direto às consultas -->
            <div class="user-actions">
                <h3>Consultas</h3>
                <a href="/TASYBackup/patients" class="btn">Consultar Pacientes</a>
            </div>

        </div>
    </div>
</body>
</html>
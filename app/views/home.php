<?php
/**
 * Servidor de contingencia ISCMC Off Grid
 *
 * Este arquivo faz parte do framework MVC Projeto Contingenciamento.
 *
 * @category Framework
 * @package  Servidor de contingencia ISCMC
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
        h1, h2, h3 {
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
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #219653;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        /* Estilos para configuracao do front-end */
        .frontend-config {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .frontend-config-grid {
            display: grid;
            grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }
        .frontend-status-panel,
        .frontend-actions-panel {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 16px;
        }
        .frontend-status-panel h3,
        .frontend-actions-panel h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }
        .config-option {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .config-option:hover {
            background: #f8f9fa;
        }
        .config-option.selected {
            background: #e8f4fd;
            border-color: #3498db;
        }
        .config-option input[type="radio"] {
            margin-right: 10px;
        }
        .config-label {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .config-description {
            color: #666;
            font-size: 0.9em;
        }
        .config-form {
            margin: 0;
        }
        .config-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-true {
            background: #ffeaa7;
            color: #d35400;
        }
        .status-false {
            background: #a8e6cf;
            color: #27ae60;
        }
        .frontend-actions {
            margin-top: 20px;
        }
        .frontend-note {
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }
        @media (max-width: 768px) {
            .frontend-config-grid {
                grid-template-columns: 1fr;
            }
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

        <div class="frontend-config">
            <h2>Configuracao do Front-end ISCMC</h2>
            <div class="frontend-config-grid">
                <div class="frontend-status-panel">
                    <h3>Status</h3>
                    <p>
                        <strong>Status atual:</strong>
                        <span class="config-status status-<?= $frontendStatus ?>">
                            <?= $frontendStatus === 'TRUE' ? 'NORMAL' : 'CONTINGENCIA' ?>
                        </span>
                    </p>
                    <p class="config-description">
                        <?= $frontendStatus === 'TRUE'
                            ? 'Usuarios estao vendo a pagina "Coming Soon".'
                            : 'Usuarios podem acessar o portal de contingencia normalmente.' ?>
                    </p>
                    <p class="frontend-note">
                        <i class="fas fa-info-circle"></i> Alteracoes sao aplicadas imediatamente.
                    </p>
                </div>

                <div class="frontend-actions-panel">
                    <form method="POST" action="/TASYBackup/?action=updateFrontendAccess" class="config-form">
                        <h3>Botoes</h3>

                        <div class="config-option <?= $frontendStatus === 'FALSE' ? 'selected' : '' ?>"
                             onclick="document.getElementById('option_false').checked = true; highlightOption(this);">
                            <input type="radio" id="option_false" name="frontend_active"
                                   value="FALSE" <?= $frontendStatus === 'FALSE' ? 'checked' : '' ?>>
                            <div>
                                <div class="config-label">
                                    <i class="fas fa-check-circle" style="color:#27ae60;"></i>
                                    Modo Contingencia
                                </div>
                                <div class="config-description">
                                    Usuarios podem acessar o Portal de Contingencia normalmente. Todas as funcionalidades estarao disponiveis.
                                </div>
                            </div>
                        </div>

                        <div class="config-option <?= $frontendStatus === 'TRUE' ? 'selected' : '' ?>"
                             onclick="document.getElementById('option_true').checked = true; highlightOption(this);">
                            <input type="radio" id="option_true" name="frontend_active"
                                   value="TRUE" <?= $frontendStatus === 'TRUE' ? 'checked' : '' ?>>
                            <div>
                                <div class="config-label">
                                    <i class="fas fa-ban" style="color:#e74c3c;"></i>
                                    Modo normal / Coming Soon
                                </div>
                                <div class="config-description">
                                    Usuarios verao a pagina "Coming Soon". Usar quando o Tasy estiver funcionando normalmente.
                                </div>
                            </div>
                        </div>

                        <div class="frontend-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Salvar Configuracao
                            </button>
                            <a href="http://10.132.16.43/ISCMC" target="_blank" class="btn">
                                <i class="fas fa-external-link-alt"></i> Testar Acesso
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                <h3>Ultima Sincronizacao</h3>
                <p><?= $systemInfo['LAST_SYNC_OVERALL'] ?: 'Nunca' ?></p>
            </div>

            <div class="info-card">
                <h3>Tabelas</h3>
                <p><strong>Ativas:</strong> <?= $systemInfo['TABLES_ACTIVE'] ?></p>
                <p><strong>Total:</strong> <?= $systemInfo['TABLES_CONFIGURED'] ?></p>
            </div>

            <div class="info-card">
                <h3>Registros</h3>
                <p><?= $systemInfo['TOTAL_RECORDS'] !== null ? number_format($systemInfo['TOTAL_RECORDS'], 0, ',', '.') : '0' ?></p>
            </div>

            <div class="info-card">
                <h3>Acesso ao Portal</h3>
                <p><strong>Status atual:</strong>
                    <span class="config-status status-<?= $frontendStatus ?>">
                        <?= $frontendStatus === 'TRUE' ? 'NORMAL' : 'CONTINGENCIA' ?>
                    </span>
                </p>
                <p class="config-description">
                    <?= $frontendStatus === 'TRUE'
                        ? 'Usuarios estao vendo a pagina "Coming Soon".'
                        : 'Usuarios podem acessar o portal normalmente.' ?>
                </p>
            </div>
        </div>

        <div class="status-box">
            <h2>Acoes Rapidas</h2>
            <a href="/TASYBackup/?action=forceSync" class="btn">Forcar Sincronizacao Completa</a>
            <a href="/TASYBackup/?action=viewLogs" class="btn">Ver Logs</a>
        </div>

        <div class="status-box">
            <h2>Status das Tabelas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Ultima Sincronizacao</th>
                        <th>Registros</th>
                        <th>Status</th>
                        <th>Acoes</th>
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
                            <a href="/TASYBackup/?action=forceSync&table=<?= $table['TABLE_NAME'] ?>" class="btn">Sincronizar</a>
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
        </div>
    </div>

    <script>
        function highlightOption(element) {
            document.querySelectorAll('.config-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="frontend_active"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.config-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    if (this.checked) {
                        this.closest('.config-option').classList.add('selected');
                    }
                });

                if (radio.checked) {
                    radio.closest('.config-option').classList.add('selected');
                }
            });

            const form = document.querySelector('.config-form');
            form.addEventListener('submit', function(e) {
                const selectedValue = document.querySelector('input[name="frontend_active"]:checked').value;

                if (selectedValue === 'TRUE') {
                    if (!confirm('ATENCAO: Voce esta prestes a BLOQUEAR o acesso ao Portal de Contingencia ISCMC.\n\n' +
                                'Todos os usuarios verao uma pagina de aviso de "Tasy funcionando Ok".\n\n' +
                                'Deseja continuar?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>

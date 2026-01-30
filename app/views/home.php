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
        
        /* NOVOS ESTILOS PARA CONFIGURAÇÃO DO FRONT-END */
        .frontend-config {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
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
        
        <!-- NOVA SEÇÃO: Configuração do Front-end ISCMC -->
        <div class="frontend-config">
            <h2>Configuração do Front-end ISCMC</h2>            
            <form method="POST" action="/TASYBackup/?action=updateFrontendAccess" class="config-form">
                <h3>Alterar Status:</h3>
                
                <!-- Opção: Acesso Liberado -->
                <div class="config-option <?= $frontendStatus === 'FALSE' ? 'selected' : '' ?>" 
                     onclick="document.getElementById('option_false').checked = true; highlightOption(this);">
                    <input type="radio" id="option_false" name="frontend_active" 
                           value="FALSE" <?= $frontendStatus === 'FALSE' ? 'checked' : '' ?>>
                    <div>
                        <div class="config-label">
                            <i class="fas fa-check-circle" style="color:#27ae60;"></i>
                            Modo Contingência
                        </div>
                        <div class="config-description">
                            Usuários podem acessar o Portal de Contingência normalmente. Todas as funcionalidades estarão disponíveis.
                        </div>
                    </div>
                </div>
                
                <!-- Opção: Acesso Pausado -->
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
                            Usuários verão a página "Coming Soon". Usar quando o Tasy estiver funcionando normalmente.
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Configuração
                    </button>
                    <a href="http://10.132.16.43/ISCMC" target="_blank" class="btn">
                        <i class="fas fa-external-link-alt"></i> Testar Acesso
                    </a>
                </div>
                
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <p><i class="fas fa-info-circle"></i> <strong>Importante:</strong> Alterações são aplicadas imediatamente.</p>
                </div>
            </form>
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
                <p><?= $systemInfo['TOTAL_RECORDS'] !== null ? number_format($systemInfo['TOTAL_RECORDS'], 0, ',', '.') : '0' ?></p>
            </div>

            <div class="info-card">
                <h3>Acesso ao Portal</h3>
                <p><strong>Status atual:</strong> 
                    <span class="config-status status-<?= $frontendStatus ?>">
                        <?= $frontendStatus === 'TRUE' ? 'NORMAL' : 'CONTINGÊNCIA' ?>
                    </span>
                </p>
                <p class="config-description">
                    <?= $frontendStatus === 'TRUE' 
                        ? 'Usuários estão vendo a página "Coming Soon".' 
                        : 'Usuários podem acessar o portal normalmente.' ?>
                </p>
            </div>
        </div>
        
        <div class="status-box">
            <h2>Ações Rápidas</h2>
            <a href="/TASYBackup/?action=forceSync" class="btn">Forçar Sincronização Completa</a>
            <a href="/TASYBackup/?action=viewLogs" class="btn">Ver Logs</a>
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
        
        <!-- Adicionar acesso direto às consultas -->
        <div class="user-actions">
            <h3>Consultas</h3>
            <a href="/TASYBackup/patients" class="btn">Consultar Pacientes</a>
        </div>
    </div>
    
    <!-- Script para melhorar a interação dos radio buttons -->
    <script>
        function highlightOption(element) {
            // Remove seleção de todos os options
            document.querySelectorAll('.config-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            // Adiciona seleção ao clicado
            element.classList.add('selected');
        }
        
        // Configurar evento de clique nos radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="frontend_active"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Remove seleção de todos
                    document.querySelectorAll('.config-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    // Seleciona o pai do radio button
                    if (this.checked) {
                        this.closest('.config-option').classList.add('selected');
                    }
                });
                
                // Configurar estado inicial
                if (radio.checked) {
                    radio.closest('.config-option').classList.add('selected');
                }
            });
            
            // Confirmação para bloquear acesso
            const form = document.querySelector('.config-form');
            form.addEventListener('submit', function(e) {
                const selectedValue = document.querySelector('input[name="frontend_active"]:checked').value;
                
                if (selectedValue === 'TRUE') {
                    if (!confirm('ATENÇÃO: Você está prestes a BLOQUEAR o acesso ao Portal de Contingência ISCMC.\n\n' +
                                'Todos os usuários verão a página uma página de aviso de "Tasy funcionando Ok".\n\n' +
                                'Deseja continuar?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
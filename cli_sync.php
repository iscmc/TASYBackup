<?php
// executar via linha de comando:
// php cli_sync.php
set_time_limit(0); // Sem limite
require_once '../TASYBackup/app/models/BackupModel.php';

$model = new BackupModel();
$result = $model->insertDataToLocal('PESSOA_FISICA');
echo "Concluído: " . json_encode($result);
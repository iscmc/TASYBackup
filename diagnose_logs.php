// diagnose_logs.php na raiz do projeto
<?php
require __DIR__ . '/app/models/BackupModel.php';

try {
    $model = new class extends BackupModel {
        public function testPermissions() {
            $this->verifyLogsDirectory();
            
            $testFiles = [
                'error.log',
                'sync.log',
                'sql_debug.log',
                'null_errors.log'
            ];
            
            foreach ($testFiles as $file) {
                $path = __DIR__ . '/logs/' . $file;
                echo "Testando {$file}... ";
                
                if (!file_exists($path)) {
                    echo "Não existe, tentando criar... ";
                    if (file_put_contents($path, "Teste") === false) {
                        throw new Exception("Falha ao criar");
                    }
                }
                
                if (!is_writable($path)) {
                    echo "Sem permissão, tentando corrigir... ";
                    if (!chmod($path, 0666)) {
                        throw new Exception("Falha ao corrigir permissões");
                    }
                }
                
                echo "OK\n";
            }
        }
    };
    
    $model->testPermissions();
    echo "Diagnóstico completo com sucesso!";
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
    file_put_contents('C:\Windows\Temp\tasy_log_diagnose.txt', $e->getMessage());
}
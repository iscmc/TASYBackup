<?php
class DatabaseConfig
{
    public static function getTablesToSync() {
    {
        // Retorne um array com os nomes das tabelas permitidas para backup
        return [
            'CPOE_ANATOMIA_PATOLOGICA',
            'CPOE_DIALISE',
            'CPOE_DIETA',
            'CPOE_GASOTERAPIA',
            'CPOE_HEMOTERAPIA',
            'CPOE_INTERVENCAO',
            'CPOE_MATERIAL',
            'CPOE_PROCEDIMENTO',
            'CPOE_RECOMENDACAO',
            'USUARIO',
            // Adicione outras tabelas necessárias
        ];
    }

    public static function getConfiguredTables() {
        return self::getTablesToSync();
    }
    
    public static function getTableConfig($tableName) {
        $tableConfigs = [
            'USUARIO' => [
                'key_column' => 'NM_USUARIO',
                'control_column' => 'DT_ATUALIZACAO'
            ],
            'CPOE_DIETA' => [
                'key_column' => 'NR_SEQUENCIA', 
                'control_column' => 'DT_ATUALIZACAO'
            ],
            // Adicione configurações para outras tabelas...
        ];
        
        return $tableConfigs[$tableName] ?? [
            'key_column' => 'NR_SEQUENCIA',
            'control_column' => 'DT_ATUALIZACAO'
        ];
    }
    
    public static function validarTabela($tableName) {
        $tabelasPermitidas = self::getTablesToSync();
        if (!in_array(strtoupper($tableName), $tabelasPermitidas)) {
            throw new Exception("Tabela não permitida para sincronização: " . $tableName);
        }
    }
}
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

class DatabaseConfig {
    // Configurações de conexão (mantenha as existentes)
    public static $tasyDb = [
        'host' => '10.250.250.214',
        'port' => '1521',
        //'service_name' => 'dbhomol.tasy', //base homologação
        'service_name' => 'dbprod.tasy', // alterado para produção
        'user' => 'ISCMC',
        //'pass' => 'VGGDHVYYZF',
        'pass' => 'FFEYXAASY',
        'charset' => 'AL32UTF8',
        'schema' => 'tasy' // schema prefix para queries
    ];

    public static $localDb = [
        'host' => 'localhost',
        'port' => '1521',
        'sid' => 'XE',
        'user' => 'SYSTEM',
        'pass' => 'K@t7y317',
        'charset' => 'AL32UTF8'
    ];

    // Configuração CORRETA das tabelas e suas colunas de controle
    public static $tablesConfig = [
        'USUARIO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NM_USUARIO'
        ],
        'CPOE_DIETA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_MATERIAL' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_PROCEDIMENTO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_GASOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_RECOMENDACAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_HEMOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_DIALISE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_INTERVENCAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ],
        'CPOE_ANATOMIA_PATOLOGICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA'
        ]
    ];

    // Método para obter apenas os nomes das tabelas
    public static function getTablesToSync() {
        return array_keys(self::$tablesConfig);
    }

    // Método para obter configuração de uma tabela específica
    public static function getTableConfig($tableName) {
        return self::$tablesConfig[$tableName] ?? null;
    }

    public static function validarTabela($tableName) {
        if (!array_key_exists($tableName, self::$tablesConfig)) {
            throw new InvalidArgumentException("Tabela {$tableName} não está configurada para sincronização");
        }
        return true;
    }

    public static function getConfiguredTables() {
        return [
            'USUARIO',
            'CPOE_DIETA',
            'CPOE_MATERIAL',
            'CPOE_PROCEDIMENTO', 
            'CPOE_GASOTERAPIA',
            'CPOE_RECOMENDACAO',
            'CPOE_HEMOTERAPIA',
            'CPOE_DIALISE',
            'CPOE_INTERVENCAO',
            'CPOE_ANATOMIA_PATOLOGICA',
        ];
    }

}
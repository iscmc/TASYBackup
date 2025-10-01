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
        'service_name' => 'dbprod.tasy', // alterado para produção
        'user' => 'ISCMC',
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

    // Configuração COMPLETA das tabelas (pelo menos as da CPOE) com horas de sincronização
    public static $tablesConfig = [
        'USUARIO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NM_USUARIO',
            'sync_hours' => 72, // Últimas 72 horas
            'schema' => 'TASY' // Schema no banco fonte
        ],
        'CPOE_DIETA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72, // Últimas 72 horas
            'schema' => 'TASY'
        ],
        'CPOE_MATERIAL' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_PROCEDIMENTO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_GASOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_RECOMENDACAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_HEMOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_DIALISE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_INTERVENCAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'PESSOA_FISICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_PESSOA_FISICA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ],
        'CPOE_ANATOMIA_PATOLOGICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY'
        ]
    ];

    // Método para obter apenas os nomes das tabelas
    public static function getTablesToSync() {
        return array_keys(self::$tablesConfig);
    }

    // Método para obter configuração de uma tabela específica
    public static function getTableConfig($tableName) {
        $config = self::$tablesConfig[$tableName] ?? null;
        
        if ($config) {
            // Garante valores padrão se não especificados
            $config['sync_hours'] = $config['sync_hours'] ?? 72;
            $config['schema'] = $config['schema'] ?? 'TASY';
        }
        
        return $config;
    }

    public static function validarTabela($tableName) {
        if (!array_key_exists($tableName, self::$tablesConfig)) {
            throw new InvalidArgumentException("Tabela {$tableName} não está configurada para sincronização");
        }
        return true;
    }

    public static function getConfiguredTables() {
        return self::getTablesToSync();
    }

    // Método auxiliar para debug
    public static function debugTableInfo($tableName) {
        $config = self::getTableConfig($tableName);
        if (!$config) {
            return "Tabela {$tableName} não encontrada na configuração";
        }
        
        $limiteTempo = date('d-M-Y H:i:s', strtotime("-{$config['sync_hours']} hours"));
        
        return [
            'table' => $tableName,
            'config' => $config,
            'time_limit' => $limiteTempo,
            'query_example' => "SELECT * FROM {$config['schema']}.{$tableName} WHERE {$config['control_column']} >= TO_TIMESTAMP('{$limiteTempo}', 'DD-MON-YYYY HH24:MI:SS')"
        ];
    }
}
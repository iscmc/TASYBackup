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

class DatabaseConfig {
    // Configuracoes de conexao (mantenha as existentes)
    public static $tasyDb = [
        'host' => '10.250.250.204',
        'port' => '1521',
        'service_name' => 'dbprod',
        'user' => 'ISCMC',
        'pass' => 'FFEYXAASY',
        'charset' => 'AL32UTF8',
        'schema' => 'tasy'
    ];

    public static $localDb = [
        'host' => 'localhost',
        'port' => '1521',
        'sid' => 'XE',
        'user' => 'SYSTEM',
        'pass' => 'K@t7y318',
        'charset' => 'AL32UTF8'
    ];

    public static $tablesConfig = [
        'SETOR_ATENDIMENTO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_SETOR_ATENDIMENTO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 10
        ],
        'USUARIO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NM_USUARIO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 20
        ],
        'UNIDADE_ATENDIMENTO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQ_INTERNO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 30
        ],
        'PESSOA_FISICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_PESSOA_FISICA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 40,
            'batch_size' => 500
        ],
        'COMPL_PESSOA_FISICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_PESSOA_FISICA',
            'key_columns' => ['CD_PESSOA_FISICA', 'NR_SEQUENCIA'],
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 50,
            'batch_size' => 500
        ],
        'MEDICO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_PESSOA_FISICA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'full',
            'sync_priority' => 60
        ],
        'ATENDIMENTO_PACIENTE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_ATENDIMENTO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 100
        ],
        'ATEND_PACIENTE_UNIDADE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQ_INTERNO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 110
        ],
        'PRESCR_MEDICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_PRESCRICAO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 120
        ],
        'EVOLUCAO_PACIENTE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'CD_EVOLUCAO',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 130,
            'batch_size' => 500
        ],
        'CPOE_ANATOMIA_PATOLOGICA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 140
        ],
        'CPOE_DIETA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 150
        ],
        'CPOE_MATERIAL' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 160
        ],
        'CPOE_PROCEDIMENTO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 170
        ],
        'CPOE_GASOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 180
        ],
        'CPOE_RECOMENDACAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 190
        ],
        'CPOE_HEMOTERAPIA' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 200
        ],
        'CPOE_DIALISE' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 210
        ],
        'CPOE_INTERVENCAO' => [
            'control_column' => 'DT_ATUALIZACAO',
            'key_column' => 'NR_SEQUENCIA',
            'sync_hours' => 72,
            'schema' => 'TASY',
            'retention_mode' => 'rolling',
            'sync_priority' => 220
        ]
    ];

    public static function getTablesToSync() {
        $tables = self::$tablesConfig;

        uasort($tables, function ($left, $right) {
            $leftPriority = $left['sync_priority'] ?? 100;
            $rightPriority = $right['sync_priority'] ?? 100;

            if ($leftPriority === $rightPriority) {
                return 0;
            }

            return $leftPriority <=> $rightPriority;
        });

        return array_keys($tables);
    }

    public static function getTableConfig($tableName) {
        $config = self::$tablesConfig[$tableName] ?? null;

        if ($config) {
            $config['sync_hours'] = $config['sync_hours'] ?? 72;
            $config['schema'] = $config['schema'] ?? 'TASY';
            $config['retention_mode'] = $config['retention_mode'] ?? 'rolling';
            $config['key_columns'] = $config['key_columns'] ?? [$config['key_column']];
            $config['sync_priority'] = $config['sync_priority'] ?? 100;
            $config['batch_size'] = $config['batch_size'] ?? null;
        }

        return $config;
    }

    public static function validarTabela($tableName) {
        if (!array_key_exists($tableName, self::$tablesConfig)) {
            throw new InvalidArgumentException("Tabela {$tableName} nao esta configurada para sincronizacao");
        }
        return true;
    }

    public static function getConfiguredTables() {
        return self::getTablesToSync();
    }

    public static function debugTableInfo($tableName) {
        $config = self::getTableConfig($tableName);
        if (!$config) {
            return "Tabela {$tableName} nao encontrada na configuracao";
        }

        $limiteTempo = date('d-M-Y H:i:s', strtotime("-{$config['sync_hours']} hours"));

        return [
            'table' => $tableName,
            'config' => $config,
            'time_limit' => $limiteTempo,
            'query_example' => "SELECT * FROM {$config['schema']}.{$tableName} WHERE {$config['control_column']} >= TO_TIMESTAMP('{$limiteTempo}', 'DD-MON-YYYY HH24:MI:SS')"
        ];
    }

    public static function getLocalConnection() {
        try {
            $tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=" . self::$localDb['host'] . ")(PORT=" . self::$localDb['port'] . "))(CONNECT_DATA=(SID=" . self::$localDb['sid'] . ")))";

            $conn = oci_connect(
                self::$localDb['user'],
                self::$localDb['pass'],
                $tns,
                self::$localDb['charset']
            );

            if (!$conn) {
                $error = oci_error();
                throw new Exception("Erro ao conectar ao Oracle XE: " . $error['message']);
            }

            return $conn;
        } catch (Exception $e) {
            error_log("DatabaseConfig getLocalConnection error: " . $e->getMessage());
            throw $e;
        }
    }
}

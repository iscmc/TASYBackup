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
 */

require_once __DIR__ . '/BaseModel.php';

class PacienteModel extends BaseModel {
    public function __construct() {
        parent::__construct('PACIENTE');
    }

    // Métodos específicos para pacientes
    public function getPatientByDocument($document) {
        return $this->fetchOne("NR_DOCUMENTO = :document", [':document' => $document]);
    }

    public function searchPatients($term, $limit = 50) {
        $term = "%{$term}%";
        return $this->fetchAll(
            "NM_PACIENTE LIKE :term OR NR_DOCUMENTO LIKE :term",
            [':term' => $term],
            "NM_PACIENTE",
            $limit
        );
    }

    public function searchPacientes($criterioBusca) {
        if (empty($criterioBusca)) {
            return [];
        }

        // Consulta em várias tabelas CPOE
        $tables = [
            'CPOE_DIETA',
            'CPOE_MATERIAL',
            'CPOE_PROCEDIMENTO',
            'CPOE_HEMOTERAPIA'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            try {
                $query = "SELECT '{$table}' AS source_table, NR_SEQUENCIA, NM_PACIENTE, 
                         DT_ATUALIZACAO FROM {$table} 
                         WHERE NM_PACIENTE LIKE :searchTerm
                         ORDER BY DT_ATUALIZACAO DESC";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':searchTerm', '%' . $criterioBusca . '%', PDO::PARAM_STR);
                $stmt->execute();
                
                // Limitar a 1000 resultados por tabela para evitar sobrecarga
                $tableResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results = array_merge($results, array_slice($tableResults, 0, 1000));
                
            } catch (PDOException $e) {
                error_log("Erro ao consultar tabela {$table}: " . $e->getMessage());
                continue;
            }
        }
        
        // Ordenar resultados por data mais recente
        usort($results, function($a, $b) {
            return strtotime($b['DT_ATUALIZACAO']) - strtotime($a['DT_ATUALIZACAO']);
        });
        
        return $results;
    }
    
    public function __destruct() {
        if ($this->conn) {
            oci_close($this->conn);
        }
    }
}
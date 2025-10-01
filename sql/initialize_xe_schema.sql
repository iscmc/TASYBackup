-- Tabela de controle de sincronização
CREATE TABLE TASY_SYNC_CONTROL (
    table_name VARCHAR2(100) PRIMARY KEY,
    last_sync TIMESTAMP,
    record_count NUMBER,
    status VARCHAR2(20) DEFAULT 'ACTIVE',
    key_column VARCHAR2(100) NOT NULL,
    sync_filter VARCHAR2(500)
);

-- Tabela de logs do sistema
CREATE TABLE TASY_SYNC_LOGS (
    log_id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    log_time TIMESTAMP DEFAULT SYSTIMESTAMP,
    message VARCHAR2(1000) NOT NULL,
    log_type VARCHAR2(20) CHECK (log_type IN ('INFO', 'WARNING', 'ERROR', 'DEBUG'))
);

-- Tabela de configurações do sistema
CREATE TABLE TASY_SYSTEM_CONFIG (
    config_key VARCHAR2(100) PRIMARY KEY,
    config_value VARCHAR2(1000),
    description VARCHAR2(500),
    last_updated TIMESTAMP DEFAULT SYSTIMESTAMP,
    updated_by VARCHAR2(50) DEFAULT 'SYSTEM'
);

-- Inserir configurações padrão
INSERT INTO TASY_SYSTEM_CONFIG (config_key, config_value, description)
VALUES ('SYNC_INTERVAL', '900', 'Intervalo entre sincronizações em segundos');

INSERT INTO TASY_SYSTEM_CONFIG (config_key, config_value, description)
VALUES ('RETRY_INTERVAL', '60', 'Intervalo entre tentativas de reconexão em segundos');

INSERT INTO TASY_SYSTEM_CONFIG (config_key, config_value, description)
VALUES ('MAX_RETRIES', '5', 'Número máximo de tentativas de reconexão');

-- Criar índices para performance
CREATE INDEX idx_sync_logs_time ON TASY_SYNC_LOGS (log_time);
CREATE INDEX idx_sync_logs_type ON TASY_SYNC_LOGS (log_type);

-- Pacote PL/SQL para auxiliar nas operações
CREATE OR REPLACE PACKAGE TASY_BACKUP_UTILS AS
    PROCEDURE reset_table_sync(p_table_name IN VARCHAR2);
    PROCEDURE disable_table_sync(p_table_name IN VARCHAR2);
    PROCEDURE enable_table_sync(p_table_name IN VARCHAR2);
    FUNCTION get_config_value(p_key IN VARCHAR2) RETURN VARCHAR2;
END TASY_BACKUP_UTILS;
/

CREATE OR REPLACE PACKAGE BODY TASY_BACKUP_UTILS AS
    PROCEDURE reset_table_sync(p_table_name IN VARCHAR2) IS
    BEGIN
        UPDATE TASY_SYNC_CONTROL 
        SET last_sync = NULL, 
            record_count = 0
        WHERE table_name = p_table_name;
        
        COMMIT;
    END;
    
    PROCEDURE disable_table_sync(p_table_name IN VARCHAR2) IS
    BEGIN
        UPDATE TASY_SYNC_CONTROL 
        SET status = 'INACTIVE'
        WHERE table_name = p_table_name;
        
        COMMIT;
    END;
    
    PROCEDURE enable_table_sync(p_table_name IN VARCHAR2) IS
    BEGIN
        UPDATE TASY_SYNC_CONTROL 
        SET status = 'ACTIVE'
        WHERE table_name = p_table_name;
        
        COMMIT;
    END;
    
    FUNCTION get_config_value(p_key IN VARCHAR2) RETURN VARCHAR2 IS
        v_value VARCHAR2(1000);
    BEGIN
        SELECT config_value INTO v_value
        FROM TASY_SYSTEM_CONFIG
        WHERE config_key = p_key;
        
        RETURN v_value;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            RETURN NULL;
    END;
END TASY_BACKUP_UTILS;
/

-- Criação de jobs para limpeza de logs (opcional)
BEGIN
    DBMS_SCHEDULER.CREATE_JOB (
        job_name        => 'PURGE_OLD_LOGS',
        job_type        => 'PLSQL_BLOCK',
        job_action      => 'BEGIN DELETE FROM TASY_SYNC_LOGS WHERE log_time < SYSTIMESTAMP - INTERVAL ''30'' DAY; COMMIT; END;',
        start_date      => SYSTIMESTAMP,
        repeat_interval => 'FREQ=DAILY; BYHOUR=2',
        enabled         => TRUE,
        comments        => 'Limpeza diária de logs antigos'
    );
END;
/